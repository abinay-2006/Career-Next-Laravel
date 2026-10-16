@extends('layouts.jobseeker')

@section('title', 'Walk-in Interviews')

@section('content')

<div class="main-content">

  <!-- ═══════════════════════════════════════════════════════════════
         PAGE HEADER
         ═══════════════════════════════════════════════════════════════ -->

  <div class="page-header">

    <div class="page-header-icon">
      <i class="fas fa-person-walking-arrow-right"></i>
    </div>

    <div class="page-header-text">
      <h1>Walk-in Interviews</h1>
      <p>Discover upcoming interview opportunities from employers.</p>
    </div>

  </div>


  <!-- ═══════════════════════════════════════════════════════════════
         SUMMARY
         ═══════════════════════════════════════════════════════════════ -->

  @if($total > 0)

  <div class="walkin-summary-grid">

    <!-- Total -->

    <div class="walkin-stat-card">

      <div class="walkin-stat-icon">
        <i class="fas fa-calendar-days"></i>
      </div>

      <div>
        <span>Total Interviews</span>
        <strong>{{ $total }}</strong>
      </div>

    </div>


    <!-- Upcoming -->

    <div class="walkin-stat-card">

      <div class="walkin-stat-icon upcoming-icon">
        <i class="fas fa-calendar-check"></i>
      </div>

      <div>
        <span>Upcoming</span>
        <strong>{{ $upcoming->count() }}</strong>
      </div>

    </div>


    <!-- Past -->

    <div class="walkin-stat-card">

      <div class="walkin-stat-icon past-icon">
        <i class="fas fa-clock-rotate-left"></i>
      </div>

      <div>
        <span>Past Interviews</span>
        <strong>{{ $past->count() }}</strong>
      </div>

    </div>

  </div>

  @endif


  <!-- ═══════════════════════════════════════════════════════════════
         UPCOMING INTERVIEWS
         ═══════════════════════════════════════════════════════════════ -->

  @if($upcoming->count() > 0)

  <div class="section-title walkin-section-title">
    <i class="fas fa-bolt"></i>
    Upcoming Opportunities
  </div>


  <div class="walkin-results-grid">

    @foreach($upcoming as $index => $walkin)

    <div class="walkin-modern-card"
      style="animation-delay: {{ $index * 0.07 }}s">

      <!-- Card Header -->

      <div class="walkin-card-header">

        <div class="walkin-company-area">

          <div class="walkin-company-logo">
            <i class="fas fa-building"></i>
          </div>

          <div>

            <h2>
              {{ $walkin->CompanyName }}
            </h2>

            <div class="walkin-job-title">
              <i class="fas fa-briefcase"></i>
              {{ $walkin->JobTitle }}
            </div>

          </div>

        </div>


        <span class="walkin-status upcoming">
          <span></span>
          Upcoming
        </span>

      </div>


      <!-- Information -->

      <div class="walkin-info-grid">

        <div class="walkin-info-item">

          <div class="walkin-info-icon">
            <i class="fas fa-users"></i>
          </div>

          <div>
            <span>Vacancies</span>
            <strong>
              {{ $walkin->Vacancy }}
            </strong>
          </div>

        </div>


        <div class="walkin-info-item">

          <div class="walkin-info-icon">
            <i class="fas fa-graduation-cap"></i>
          </div>

          <div>
            <span>Minimum Qualification</span>
            <strong>
              {{ $walkin->MinQualification }}
            </strong>
          </div>

        </div>

      </div>


      <!-- Description -->

      @if(!empty($walkin->Description))

      <div class="walkin-description">

        <div class="walkin-description-title">
          <i class="fas fa-file-lines"></i>
          Interview Details
        </div>

        <p>
          {{ $walkin->Description }}
        </p>

      </div>

      @endif


      <!-- Date / Time -->

      <div class="walkin-datetime">

        <div class="walkin-date-time-item">

          <div class="walkin-date-icon">
            <i class="fas fa-calendar-check"></i>
          </div>

          <div>

            <span>Interview Date</span>

            <strong>
              {{ \Carbon\Carbon::parse($walkin->InterviewDate)->format('d M Y') }}
            </strong>

          </div>

        </div>


        <div class="walkin-date-divider"></div>


        <div class="walkin-date-time-item">

          <div class="walkin-date-icon">
            <i class="fas fa-clock"></i>
          </div>

          <div>

            <span>Interview Time</span>

            <strong>
              {{ \Carbon\Carbon::parse($walkin->InterviewTime)->format('h:i A') }}
            </strong>

          </div>

        </div>

      </div>

    </div>

    @endforeach

  </div>

  @endif


  <!-- ═══════════════════════════════════════════════════════════════
         NO UPCOMING
         ═══════════════════════════════════════════════════════════════ -->

  @if($total > 0 && $upcoming->count() === 0)

  <div class="walkin-no-upcoming">

    <div class="walkin-no-upcoming-icon">
      <i class="fas fa-calendar-xmark"></i>
    </div>

    <div>

      <h3>No Upcoming Walk-in Interviews</h3>

      <p>
        There are currently no upcoming walk-in drives.
        Check back later for new opportunities.
      </p>

    </div>

  </div>

  @endif


  <!-- ═══════════════════════════════════════════════════════════════
         PAST INTERVIEWS
         ═══════════════════════════════════════════════════════════════ -->

  @if($past->count() > 0)

  <div class="section-title walkin-section-title past-heading">
    <i class="fas fa-clock-rotate-left"></i>
    Previous Walk-ins
  </div>


  <div class="walkin-results-grid past-grid">

    @foreach($past as $walkin)

    <div class="walkin-modern-card past-card">

      <!-- Card Header -->

      <div class="walkin-card-header">

        <div class="walkin-company-area">

          <div class="walkin-company-logo past-logo">
            <i class="fas fa-building"></i>
          </div>

          <div>

            <h2>
              {{ $walkin->CompanyName }}
            </h2>

            <div class="walkin-job-title">
              <i class="fas fa-briefcase"></i>
              {{ $walkin->JobTitle }}
            </div>

          </div>

        </div>


        <span class="walkin-status past">
          <span></span>
          Completed
        </span>

      </div>


      <!-- Information -->

      <div class="walkin-info-grid">

        <div class="walkin-info-item">

          <div class="walkin-info-icon">
            <i class="fas fa-users"></i>
          </div>

          <div>
            <span>Vacancies</span>
            <strong>
              {{ $walkin->Vacancy }}
            </strong>
          </div>

        </div>


        <div class="walkin-info-item">

          <div class="walkin-info-icon">
            <i class="fas fa-graduation-cap"></i>
          </div>

          <div>
            <span>Minimum Qualification</span>
            <strong>
              {{ $walkin->MinQualification }}
            </strong>
          </div>

        </div>

      </div>


      @if(!empty($walkin->Description))

      <div class="walkin-description">

        <div class="walkin-description-title">
          <i class="fas fa-file-lines"></i>
          Interview Details
        </div>

        <p>
          {{ $walkin->Description }}
        </p>

      </div>

      @endif


      <!-- Date / Time -->

      <div class="walkin-datetime past-datetime">

        <div class="walkin-date-time-item">

          <div class="walkin-date-icon">
            <i class="fas fa-calendar-xmark"></i>
          </div>

          <div>

            <span>Interview Date</span>

            <strong>
              {{ \Carbon\Carbon::parse($walkin->InterviewDate)->format('d M Y') }}
            </strong>

          </div>

        </div>


        <div class="walkin-date-divider"></div>


        <div class="walkin-date-time-item">

          <div class="walkin-date-icon">
            <i class="fas fa-clock"></i>
          </div>

          <div>

            <span>Interview Time</span>

            <strong>
              {{ \Carbon\Carbon::parse($walkin->InterviewTime)->format('h:i A') }}
            </strong>

          </div>

        </div>

      </div>

    </div>

    @endforeach

  </div>

  @endif


  <!-- ═══════════════════════════════════════════════════════════════
         COMPLETELY EMPTY
         ═══════════════════════════════════════════════════════════════ -->

  @if($total === 0)

  <div class="walkin-empty-state">

    <div class="walkin-empty-icon">
      <i class="fas fa-person-walking-arrow-right"></i>
    </div>

    <h2 style="color: #fff;">No Walk-in Interviews Available</h2>

    <p>
      There are currently no walk-in interviews scheduled.
      New opportunities will appear here when employers publish
      their interview drives.
    </p>

  
  </div>

  @endif

</div>

@endsection