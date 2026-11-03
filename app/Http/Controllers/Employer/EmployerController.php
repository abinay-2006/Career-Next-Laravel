<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\DashboardAnalyticsService;

class EmployerController extends Controller
{
    public function index(DashboardAnalyticsService $analytics)
    {
        $employer = Auth::guard('employer')->user();
        abort_unless($employer, 403);

        /*
        |--------------------------------------------------------------------------
        | PROFILE COMPLETION
        |--------------------------------------------------------------------------
        | Every item is a column the Company Profile page already lets the employer
        | edit, so anything listed as missing can actually be completed.
        */

        $completionItems = [
            'company_name' => !empty(trim((string) $employer->CompanyName)),

            'email' => !empty(trim((string) $employer->Email)),

            'mobile' => !empty(trim((string) $employer->Mobile)),

            'contact_person' => !empty(trim((string) $employer->ContactPerson)),

            'area_work' => !empty(trim((string) $employer->Area_Work)),

            'city' => !empty(trim((string) $employer->City)),

            'address' => !empty(trim((string) $employer->Address)),

            'photo' => !empty(trim((string) $employer->Photo)),
        ];

        $completedItems = count(
            array_filter($completionItems)
        );

        $totalItems = count($completionItems);

        $profileCompletion = $totalItems > 0
            ? (int) round(($completedItems / $totalItems) * 100)
            : 0;

        $profileCompletionLabels = [
            'company_name' => [
                'label' => 'Company Name',
                'route' => 'employer.profile.index',
            ],

            'email' => [
                'label' => 'Email',
                'route' => 'employer.profile.index',
            ],

            'mobile' => [
                'label' => 'Mobile Number',
                'route' => 'employer.profile.index',
            ],

            'contact_person' => [
                'label' => 'Contact Person',
                'route' => 'employer.profile.index',
            ],

            'area_work' => [
                'label' => 'Area of Work',
                'route' => 'employer.profile.index',
            ],

            'city' => [
                'label' => 'City',
                'route' => 'employer.profile.index',
            ],

            'address' => [
                'label' => 'Address',
                'route' => 'employer.profile.index',
            ],

            'photo' => [
                'label' => 'Company Logo',
                'route' => 'employer.profile.index',
            ],
        ];

        $missingProfileItems = [];

        foreach ($completionItems as $key => $completed) {

            if (!$completed) {

                $missingProfileItems[] = [
                    'key' => $key,
                    'label' => $profileCompletionLabels[$key]['label'],
                    'route' => $profileCompletionLabels[$key]['route'],
                ];
            }
        }

        return view('employer.index', [
            'analytics' => $analytics->employer((int) $employer->EmployerId),
            'completionItems' => $completionItems,
            'completedItems' => $completedItems,
            'totalItems' => $totalItems,
            'profileCompletion' => $profileCompletion,
            'missingProfileItems' => $missingProfileItems,
        ]);
    }
}
