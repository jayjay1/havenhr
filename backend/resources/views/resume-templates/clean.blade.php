<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $content['personal_info']['name'] ?? 'Resume' }}</title>
    <style>
        /* Clean Template — Minimal, lots of whitespace, simple typography */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #333333;
            padding: 40px 50px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #cccccc;
        }
        .header h1 {
            font-size: 22pt;
            font-weight: 400;
            color: #222222;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .contact-info {
            font-size: 9pt;
            color: #666666;
        }
        .contact-info span {
            margin: 0 6px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 12pt;
            font-weight: 600;
            color: #222222;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #dddddd;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }
        .summary {
            font-size: 10pt;
            color: #555555;
            line-height: 1.6;
        }
        .entry {
            margin-bottom: 12px;
        }
        .entry-header {
            display: table;
            width: 100%;
            margin-bottom: 3px;
        }
        .entry-title {
            display: table-cell;
            font-weight: 600;
            font-size: 11pt;
            color: #222222;
        }
        .entry-date {
            display: table-cell;
            text-align: right;
            font-size: 9pt;
            color: #888888;
        }
        .entry-subtitle {
            font-size: 10pt;
            color: #555555;
            margin-bottom: 4px;
        }
        .bullets {
            list-style-type: disc;
            margin-left: 18px;
            font-size: 10pt;
            color: #444444;
        }
        .bullets li {
            margin-bottom: 3px;
        }
        .skills-list {
            font-size: 10pt;
            color: #444444;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    {{-- Personal Info Header --}}
    <div class="header">
        <h1>{{ $content['personal_info']['name'] ?? '' }}</h1>
        <div class="contact-info">
            @php
                $contactParts = array_filter([
                    $content['personal_info']['email'] ?? '',
                    $content['personal_info']['phone'] ?? '',
                    $content['personal_info']['location'] ?? '',
                ]);
            @endphp
            {{ implode('  |  ', $contactParts) }}
            @if(!empty($content['personal_info']['linkedin_url']))
                <br>{{ $content['personal_info']['linkedin_url'] }}
            @endif
            @if(!empty($content['personal_info']['portfolio_url']))
                &nbsp;|&nbsp;{{ $content['personal_info']['portfolio_url'] }}
            @endif
        </div>
    </div>

    {{-- Summary --}}
    @if(!empty($content['summary']))
    <div class="section">
        <div class="section-title">Professional Summary</div>
        <div class="summary">{{ $content['summary'] }}</div>
    </div>
    @endif

    {{-- Work Experience --}}
    @if(!empty($content['work_experience']))
    <div class="section">
        <div class="section-title">Work Experience</div>
        @foreach($content['work_experience'] as $job)
        <div class="entry">
            <div class="entry-header">
                <span class="entry-title">{{ $job['job_title'] ?? '' }}</span>
                <span class="entry-date">{{ $job['start_date'] ?? '' }} — {{ $job['end_date'] ?? 'Present' }}</span>
            </div>
            <div class="entry-subtitle">{{ $job['company_name'] ?? '' }}</div>
            @if(!empty($job['bullets']))
            <ul class="bullets">
                @foreach($job['bullets'] as $bullet)
                <li>{{ $bullet }}</li>
                @endforeach
            </ul>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    {{-- Education --}}
    @if(!empty($content['education']))
    <div class="section">
        <div class="section-title">Education</div>
        @foreach($content['education'] as $edu)
        <div class="entry">
            <div class="entry-header">
                <span class="entry-title">{{ $edu['degree'] ?? '' }}{{ !empty($edu['field_of_study']) ? ' in ' . $edu['field_of_study'] : '' }}</span>
                <span class="entry-date">{{ $edu['start_date'] ?? '' }} — {{ $edu['end_date'] ?? 'Present' }}</span>
            </div>
            <div class="entry-subtitle">{{ $edu['institution_name'] ?? '' }}</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Skills --}}
    @if(!empty($content['skills']))
    <div class="section">
        <div class="section-title">Skills</div>
        <div class="skills-list">
            {{ implode('  •  ', $content['skills']) }}
        </div>
    </div>
    @endif
</body>
</html>
