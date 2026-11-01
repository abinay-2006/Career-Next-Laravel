<?php

namespace Tests\Feature;

use App\Models\JobSeeker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResumeBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName'); $table->string('Email'); $table->string('Mobile');
            $table->string('City')->nullable(); $table->string('Address')->nullable();
            $table->string('Qualification')->nullable(); $table->string('Gender')->nullable();
            $table->date('BirthDate')->nullable(); $table->string('Photo')->nullable();
            $table->string('Status')->nullable(); $table->string('UserName')->nullable(); $table->string('Password')->nullable();
        });
        Schema::create('jobseeker_education', function (Blueprint $table): void {
            $table->increments('EduId'); $table->integer('JobSeekId'); $table->string('Degree')->nullable();
            $table->string('University')->nullable(); $table->integer('PassingYear')->nullable(); $table->float('Percentage')->nullable();
        });
        Schema::create('jobseeker_school_education', function (Blueprint $table): void {
            $table->increments('id'); $table->integer('JobSeekId'); $table->string('SchoolName')->nullable();
            $table->string('Qualification')->nullable(); $table->string('Board')->nullable();
            $table->integer('YearOfPassing')->nullable(); $table->decimal('Percentage', 5, 2)->nullable();
        });
        Schema::create('jobseeker_resumes', function (Blueprint $table): void {
            $table->id(); $table->integer('JobSeekId'); $table->string('title'); $table->string('template');
            $table->json('content'); $table->timestamps();
        });
    }

    private function seeker(string $name): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => $name, 'Email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'Mobile' => '9876543210', 'City' => 'Pune', 'Address' => 'Central Pune',
            'Qualification' => 'B.Sc', 'Gender' => 'Other', 'BirthDate' => '2000-11-01', 'Status' => 'Approved',
        ]);
        return JobSeeker::findOrFail($id);
    }

    public function test_builder_imports_profile_and_education_without_editing_profile(): void
    {
        $seeker = $this->seeker('Resume Owner');
        DB::table('jobseeker_education')->insert([
            'JobSeekId' => $seeker->JobSeekId, 'Degree' => 'B.Sc', 'University' => 'Example University', 'PassingYear' => 2022, 'Percentage' => 82.5,
        ]);

        $page = $this->actingAs($seeker, 'jobseeker')->get(route('jobseeker.resumes.index'))
            ->assertOk()->assertSee('Resume Owner')->assertSee('Example University')->assertSee('Age')
            ->assertSee(route('jobseeker.resumes.preview'));
        $this->assertStringContainsString("document.createElement('label')", $page->getContent());
        $this->assertStringNotContainsString("make('label')", $page->getContent());
        $this->assertStringNotContainsString("getElementById('templateSelect')", $page->getContent());
        $this->assertStringContainsString('data-template-option', $page->getContent());
        $this->assertSame('Resume Owner', $seeker->fresh()->JobSeekerName);
    }

    public function test_resume_can_be_saved_and_downloaded_as_a_pdf(): void
    {
        $seeker = $this->seeker('PDF Owner');
        $payload = ['title' => 'Analyst Resume', 'template' => 'ats', 'content' => json_encode([
            'name' => 'PDF Owner', 'email' => 'pdf.owner@example.test', 'phone' => '9876543210',
            'location' => 'Pune', 'age' => 25, 'qualification' => 'B.Sc', 'sections' => ['summary'],
            'summary' => '<img src=x onerror=alert(1)>',
            'education' => [
                ['qualification' => 'B.Sc', 'institution' => 'North University', 'board' => '', 'year' => '2022', 'result' => '82%'],
                ['qualification' => 'HSC', 'institution' => 'Central College', 'board' => 'State Board', 'year' => '2019', 'result' => '90%'],
            ],
            'experience' => [
                ['title' => 'Analyst', 'company' => 'Example Inc', 'duration' => '2023-present', 'responsibilities' => 'Reporting'],
                ['title' => 'Intern', 'company' => 'Sample Ltd', 'duration' => '2022', 'responsibilities' => 'Research'],
            ],
            'skills' => 'SQL, Reporting', 'certifications' => [['name' => 'Excel Pro', 'issuer' => 'Example', 'year' => '2024']],
            'projects' => [['name' => 'Market study', 'description' => 'A sample project', 'link' => '']],
        ])];
        $response = $this->actingAs($seeker, 'jobseeker')->postJson(route('jobseeker.resumes.store'), $payload)->assertOk();
        $id = $response->json('id');
        $this->assertDatabaseHas('jobseeker_resumes', ['id' => $id, 'JobSeekId' => $seeker->JobSeekId, 'template' => 'ats']);
        $this->get(route('jobseeker.resumes.index', ['resume' => $id]))
            ->assertOk()->assertSee('Analyst Resume')->assertSee('North University')->assertSee('Example Inc');

        foreach (['classic', 'modern', 'ats', 'creative', 'corporate'] as $template) {
            $payload['id'] = $id;
            $payload['template'] = $template;
            $this->postJson(route('jobseeker.resumes.store'), $payload)->assertOk();
            $this->assertDatabaseHas('jobseeker_resumes', ['id' => $id, 'template' => $template]);
            $preview = $this->postJson(route('jobseeker.resumes.preview'), [
                'template' => $template,
                'content' => $payload['content'],
            ])->assertOk()->json('html');
            $this->assertStringContainsString('resume-document resume-'.$template, $preview);
            $this->assertStringContainsString('resume-section', $preview);
            $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $preview);
            $pdf = $this->get(route('jobseeker.resumes.download', $id))->assertOk();
            $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        }
    }

    public function test_create_action_makes_an_additional_resume_without_replacing_the_first(): void
    {
        $seeker = $this->seeker('Multi Resume');
        $payload = ['title' => 'Second version', 'template' => 'creative', 'content' => json_encode([
            'name' => 'Multi Resume', 'sections' => [], 'education' => [], 'experience' => [],
        ])];
        $first = $this->actingAs($seeker, 'jobseeker')->postJson(route('jobseeker.resumes.create'), $payload)->assertOk()->json('id');
        $payload['title'] = 'Third version';
        $second = $this->postJson(route('jobseeker.resumes.create'), $payload)->assertOk()->json('id');

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('jobseeker_resumes', 2);
    }

    public function test_job_seeker_cannot_download_another_seeker_resume(): void
    {
        $owner = $this->seeker('Resume Owner');
        $other = $this->seeker('Other Seeker');
        $id = DB::table('jobseeker_resumes')->insertGetId([
            'JobSeekId' => $owner->JobSeekId, 'title' => 'Private', 'template' => 'classic',
            'content' => json_encode(['name' => 'Resume Owner', 'sections' => []]), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($owner, 'jobseeker')->get(route('jobseeker.my-resumes.show', $id))->assertOk();
        $this->get(route('jobseeker.my-resumes.preview', $id))->assertOk()->assertSee('resume-document');
        $this->actingAs($other, 'jobseeker')->get(route('jobseeker.resumes.download', $id))->assertNotFound();
        $this->deleteJson(route('jobseeker.resumes.destroy', $id))->assertNotFound();
        $this->get(route('jobseeker.my-resumes.show', $id))->assertNotFound();
        $this->get(route('jobseeker.my-resumes.preview', $id))->assertNotFound();
    }

    public function test_resume_owner_can_delete_from_my_resumes(): void
    {
        $owner = $this->seeker('Delete Owner');
        $id = DB::table('jobseeker_resumes')->insertGetId([
            'JobSeekId' => $owner->JobSeekId, 'title' => 'Remove me', 'template' => 'classic',
            'content' => json_encode(['name' => 'Delete Owner', 'sections' => []]), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($owner, 'jobseeker')->deleteJson(route('jobseeker.resumes.destroy', $id))->assertOk();
        $this->assertDatabaseMissing('jobseeker_resumes', ['id' => $id]);
    }

    public function test_my_resumes_lists_and_searches_only_the_authenticated_seekers_records(): void
    {
        $mine = $this->seeker('My Account');
        $other = $this->seeker('Other Account');
        foreach ([[$mine, 'Analyst Resume'], [$mine, 'Designer Resume'], [$other, 'Private Resume']] as [$seeker, $title]) {
            DB::table('jobseeker_resumes')->insert([
                'JobSeekId' => $seeker->JobSeekId, 'title' => $title, 'template' => 'classic',
                'content' => json_encode(['name' => $seeker->JobSeekerName, 'sections' => []]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($mine, 'jobseeker')->get(route('jobseeker.my-resumes.index'))
            ->assertOk()->assertSee('My Resumes')->assertSee('Analyst Resume')->assertSee('Designer Resume')->assertDontSee('Private Resume');
        $this->get(route('jobseeker.my-resumes.index', ['q' => 'Analyst']))
            ->assertOk()->assertSee('Analyst Resume')->assertDontSee('Designer Resume')->assertDontSee('Private Resume');
    }

    public function test_my_resumes_empty_state_and_create_new_open_a_fresh_builder(): void
    {
        $seeker = $this->seeker('New Resume Seeker');
        $this->actingAs($seeker, 'jobseeker')->get(route('jobseeker.my-resumes.index'))
            ->assertOk()->assertSee("You haven't created any resumes yet.", false)->assertSee('Create Your First Resume');
        $this->get(route('jobseeker.resumes.index', ['new' => 1]))
            ->assertOk()->assertSee('data-resume-id=""', false)->assertSee('value="My Resume"', false);
    }
}
