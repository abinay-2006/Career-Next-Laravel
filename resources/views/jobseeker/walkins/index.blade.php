@extends('layouts.jobseeker')

@section('title','Jobseeker Portal')

@section('content')

<!-- ══ MAIN ══ -->
  <div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-icon"><i class="fas fa-person-walking-arrow-right"></i></div>
      <div class="page-header-text">
        <h1>Walk-in Interviews</h1>
        <p>Upcoming and recent walk-in interview drives you can attend</p>
      </div>
      <a href="{{ route('jobseeker.index') }}" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <!-- Summary bar -->
    @if ($total > 0)
      <div class="summary-bar">
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-calendar-days"></i></div>
          <div>
            <div class="stat-value">{{ $total }}</div>
            <div class="stat-label">Total Interviews</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:rgba(74,222,128,.18)"><i class="fas fa-circle-check" style="color:#86efac"></i></div>
          <div>
            <div class="stat-value">{{ $upcoming->count() }}</div>
            <div class="stat-label">Upcoming</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:rgba(156,163,175,.15)"><i class="fas fa-clock-rotate-left" style="color:rgba(255,255,255,.5)"></i></div>
          <div>
            <div class="stat-value" style="color:rgba(255,255,255,.6)">{{ $past->count() }}</div>
            <div class="stat-label">Past</div>
          </div>
        </div>
      </div>
    @endif

    @if ($total > 0)

      <!-- Upcoming -->
      @if (count($upcoming) > 0)
        <div class="section-title"><i class="fas fa-bolt"></i> Upcoming Walk-ins</div>
        <div class="cards-grid" style="margin-bottom:32px">
          @foreach ($upcoming->values() as $i => $w)
            <div class="walkin-card" style="animation-delay:0.07s">

              <div class="card-top">
                <div class="card-company-wrap">
                  <div class="card-company">{{ $w->CompanyName }}</div>
                  <div class="card-job-title">
                    <i class="fas fa-briefcase" style="font-size:.7rem;opacity:.6"></i>
                    {{ $w->JobTitle }}
                  </div>
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
                  <div class="card-logo"><i class="fas fa-building"></i></div>
                  <span class="timing-badge upcoming"><i class="fas fa-circle" style="font-size:.45rem"></i> Upcoming</span>
                </div>
              </div>

              <div class="card-divider"></div>

              <div class="details-grid">
                <div class="detail-item">
                  <span class="detail-label"><i class="fas fa-users"></i> Vacancies</span>
                  <span class="vacancy-pill" style="width:fit-content"><i class="fas fa-user-plus" style="font-size:.68rem"></i> {{ $w->Vacancy }} Open</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label"><i class="fas fa-graduation-cap"></i> Qualification</span>
                  <span class="qual-badge" style="width:fit-content">{{ $w->MinQualification }}</span>
                </div>
              </div>

              @if (!empty($w['Description']))
                <div class="card-desc">{{ $w->Description }}</div>
              @endif

              <div class="datetime-strip">
                <div class="datetime-block">
                  <i class="fas fa-calendar-check"></i>
                  <div class="datetime-text">
                    <div class="dt-label">Interview Date</div>
                    <div class="dt-value">{{ \Carbon\Carbon::parse($w->InterviewDate)->format('d M Y') }}</div>
                  </div>
                </div>
                <div class="dt-sep"></div>
                <div class="datetime-block">
                  <i class="fas fa-clock"></i>
                  <div class="datetime-text">
                    <div class="dt-label">Interview Time</div>
                    <div class="dt-value">{{ \Carbon\Carbon::parse($w->InterviewTime)->format('h:i A') }}</div>
                  </div>
                </div>
              </div>

            </div>
          @endforeach
        </div>
      @endif

      <!-- Past -->
      @if (count($past) > 0)
        <div class="section-title"><i class="fas fa-clock-rotate-left"></i> Past Walk-ins</div>
        <div class="cards-grid">
          @foreach ($past->values() as $i => $w)
            <div class="walkin-card past" style="opacity:.7;animation-delay:0s">

              <div class="card-top">
                <div class="card-company-wrap">
                  <div class="card-company">{{ $w->CompanyName }}</div>
                  <div class="card-job-title">
                    <i class="fas fa-briefcase" style="font-size:.7rem;opacity:.6"></i>
                    {{ $w->JobTitle }}
                  </div>
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
                  <div class="card-logo" style="background:rgba(255,255,255,.08)"><i class="fas fa-building" style="opacity:.5"></i></div>
                  <span class="timing-badge past"><i class="fas fa-circle" style="font-size:.45rem"></i> Past</span>
                </div>
              </div>

              <div class="card-divider"></div>

              <div class="details-grid">
                <div class="detail-item">
                  <span class="detail-label"><i class="fas fa-users"></i> Vacancies</span>
                  <span class="detail-value">{{ $w->Vacancy }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label"><i class="fas fa-graduation-cap"></i> Qualification</span>
                  <span class="detail-value">{{ $w->MinQualification }}</span>
                </div>
              </div>

              @if (!empty($w['Description']))
                <div class="card-desc">{{ $w->Description }}</div>
              @endif

              <div class="datetime-strip" style="opacity:.6">
                <div class="datetime-block">
                  <i class="fas fa-calendar-xmark"></i>
                  <div class="datetime-text">
                    <div class="dt-label">Interview Date</div>
                    <div class="dt-value">{{ $w->InterviewDate }}</div>
                  </div>
                </div>
                <div class="dt-sep"></div>
                <div class="datetime-block">
                  <i class="fas fa-clock"></i>
                  <div class="datetime-text">
                    <div class="dt-label">Interview Time</div>
                    <div class="dt-value">{{ $w->InterviewTime }}</div>
                  </div>
                </div>
              </div>

            </div>
          @endforeach
        </div>
      @endif

    @else
      <div class="empty-state">
        <i class="fas fa-person-walking-arrow-right"></i>
        <p>No walk-in interviews scheduled yet.</p>
        <span>Check back later for upcoming walk-in drives from companies.</span>
      </div>
    @endif

  </div><!-- /main-content -->
  @endsection

  @section('scripts')