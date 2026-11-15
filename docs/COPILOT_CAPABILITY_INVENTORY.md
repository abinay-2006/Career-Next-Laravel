# Career Next Copilot capability inventory

This inventory was checked against `routes/web.php`, the role middleware, and the controllers and services listed below. It describes application functionality; it does not imply that Copilot can execute every listed action.

## Admin

**Existing application capabilities**

- Review dashboard totals and summaries.
- Search and review Job Seeker and Employer accounts; approve, suspend, and delete accounts (`ManageUsersController`).
- Review jobs and approve or reject them (`ManageJobsController`).
- Review uploaded documents and approve or reject them (`ManageDocumentsController`).
- Manage news, review feedback, and filter activity logs (`ManageNewsController`, `ManageFeedbackController`, `ActivityLogController`).
- Inspect recent successful login events from the existing `logs` activity trail.
- All Admin routes are inside `role:admin` middleware. The application does not define finer-grained Admin permissions.

**Copilot access**

- Can summarize only the server-built facts in its context.
- Can request one write operation: suspend one exact Job Seeker or Employer account. Laravel resolves the exact account, checks the Admin guard, asks for `CONFIRM SUSPENSION`, expires that confirmation after five minutes, applies the existing account status operation, and reports the actual result.
- Account approval/deletion, job review, and document review remain available in their existing Admin pages. Copilot does not execute those changes.

## Employer

**Existing application capabilities**

- View employer analytics and manage only their own job postings; create, update, and delete postings (`EmployerJobController`, `DashboardAnalyticsController`). New postings start pending and job fields are validated by the application.
- Run a read-only quality check on proposed job details (`JobQualityController`, `JobQualityService`).
- Review applicants to their own jobs and set application status to `Pending`, `Accepted`, or `Rejected` (`EmployerApplicationController`).
- Schedule, revise, cancel, and complete interviews for eligible applications, with service-level ownership and state checks (`EmployerInterviewController`, `ApplicationInterviewService`).
- Use application chat, view delivered follow-up messages, mark those messages seen, and update their profile.
- Employer routes use `role:employer`; job and application access is scoped to the authenticated employer.

**Copilot access**

- Context is limited to the authenticated employer's own job and application summaries.
- No Copilot write operation is enabled. The assistant may explain the existing workflow, but cannot claim it posted a job, changed an application, or scheduled an interview.

## Job Seeker

**Existing application capabilities**

- Browse approved jobs, apply, view own application history, save jobs, compare jobs, and use matching, suitability, and roadmap guidance (`JobSearchController`, `JobApplicationController`, `SavedJobController`, `JobComparisonController`, `JobSuitabilityService`, `CareerRoadmapService`).
- Manage own profile, resume, skills, education, school records, documents, and career preferences.
- Confirm or decline own interview invitations, or request a reschedule (`JobSeekerInterviewController`). A reschedule request does not change the scheduled time.
- Use application chat and the follow-up coach; save coach preferences, record personal follow-up activity, and deliberately send an edited follow-up message.
- Job Seeker routes use `role:jobseeker`; personal data and actions are scoped to the authenticated account.

**Copilot access**

- Context is limited to the authenticated Job Seeker's profile, skills, resume, applications, and relevant approved jobs.
- No Copilot write operation is enabled. The assistant may explain the existing workflow, but cannot claim it applied, saved a job, edited a profile, or sent a follow-up.

## Security boundary

FreeLLMAPI is the sole model provider. The model has no database handle, SQL interface, arbitrary URL fetcher, shell, or action endpoint. Its function proposals are checked against the active role, strict parameter types, the authenticated account, ownership, and existing business rules before Laravel executes a registered query or service.

**Admin functions:** get_dashboard_statistics, get_registration_statistics, get_recent_login_activity, get_application_statistics, get_recent_jobs, get_pending_document_reviews, get_user_details, and suspend_user. Registration trends return unavailable because the current account tables have no registration timestamps. Login history comes only from existing successful events, limited to 30 days and 50 entries. Account lookup returns role, numeric ID, name, and status only. Suspension requires a one-time, authenticated, five-minute confirmation.

**Employer functions:** get_employer_dashboard_statistics, get_my_jobs, get_employer_job_applicants, get_my_interviews, and get_my_employer_profile. Each job/application query is scoped to the authenticated EmployerId; interview queries also verify the interview creator. Applicant output is limited to reference, status, and applied date. There are no Employer write functions.

**Job Seeker functions:** get_my_profile, get_my_applications, get_my_saved_jobs, get_my_interviews, and get_my_resume_summary. Each query is scoped to the authenticated JobSeekId. Resume output contains metadata only; resume contents and uploaded documents are not sent. There are no Job Seeker write functions.

Parallel calls are rejected; no more than three function calls are processed per request. Only the Admin suspension function proposes a write, and the user-facing result for that action comes from Laravel's confirmation flow.
