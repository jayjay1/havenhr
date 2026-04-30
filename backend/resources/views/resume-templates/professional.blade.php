<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $content['personal_info']['name'] ?? 'Resume' }}</title>
    <style>
        /* Professional Template — Traditional, serif fonts, formal layout */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #1a1a1a;
            padding: 40px 50px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #1a1a1a;
        }
        .header h1 {
            font-size: 20pt;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .contact-info {
            font-size: 9pt;
            color: #444444;
            font-family: 'Helvetica', 'Arial', sans-serif;
        }
        .section {
            margin-bottom: 18px;
        }
        .section-title {
            font-size: 12pt;
            font-weight: 700;
            color: #1a1a1a;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border-bottom: 1px solid #999999;
            padding-bottom: 3px;
            margin-bottom: 10px;
        }
        .summary {
            font-size: 10.5pt;
            color: #333333;
            line-height: 1.6;
            font-style: italic;
        }
        .entry {
            margin-bottom: 14px;
        }
        .entry-header {
            display: table;
            width: 100%;
            margin-bottom: 2px;
        }
        .entry-left {
            display: table-cell;
            vertical-align: top;
        }
        .entry-right {
            display: table-cell;
            text-align: right;
            vertical-align: top;
            white-space: nowrap;
        }
        .entry-title {
            font-weight: 700;
            font-size: 11pt;
            color: #1a1a1a;
        }
        .entry-company {
            font-style: italic;
            font-size: 10.5pt;
            color: #333333;
        }
        .entry-date {
            font-size: 9.5pt;
            color: #666666;
            font-family: 'Helvetica', 'Arial', sans-serif;
        }
        .bullets {
            list-style-type: disc;
            margin-left: 20px;
            margin-top: 4px;
            font-size: 10pt;
            color: #333333;
        }
        .bullets li {
            margin-bottom: 3px;
            line-height: 1.5;
        }
        .edu-entry {
            margin-bottom: 10px;
        }
        .edu-header {
            display: table;
            width: 100%;
        }
        .edu-left {
            display: table-cell;
            vertical-align: top;
        }
        .edu-right {
            display: table-cell;
            text-align: right;
            vertical-align: top;
        }
        .edu-degree {
            font-weight: 700;
            font-size: 11pt;
        }
        .edu-school {
            font-style: italic;
            font-size: 10pt;
            color: #444444;
        }
        .skills-section {
            font-size: 10pt;
            color: #333333;
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
            {{ implode('  ·  ', $contactParts) }}
            @if(!empty($content['personal_info']['linkedin_url']))
                <br>{{ $content['personal_info']['linkedin_url'] }}
            @endif
            @if(!empty($content['personal_info']['portfolio_url']))
                &nbsp;·&nbsp;{{ $content['personal_info']['portfolio_url'] }}
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
        <div class="section-title">Professional Experience</div>
        @foreach($content['work_experience'] as $job)
        <div class="entry">
            <div class="entry-header">
                <div class="entry-left">
                    <div class="entry-title">{{ $job['job_title'] ?? '' }}</div>
                    <div class="entry-company">{{ $job['company_name'] ?? '' }}</div>
                </div>
                <div class="entry-right">
                    <div class="entry-date">{{ $job['start_date'] ?? '' }} — {{ $job['end_date'] ?? 'Present' }}</div>
                </div>
            </div>
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
        <div class="edu-entry">
            <div class="edu-header">
                <div class="edu-left">
                    <div class="edu-degree">{{ $edu['degree'] ?? '' }}{{ !empty($edu['field_of_study']) ? ', ' . $edu['field_of_study'] : '' }}</div>
                    <div class="edu-school">{{ $edu['institution_name'] ?? '' }}</div>
                </div>
                <div class="edu-right">
                    <div class="entry-date">{{ $edu['start_date'] ?? '' }} — {{ $edu['end_date'] ?? 'Present' }}</div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Skills --}}
    @if(!empty($content['skills']))
    <div class="section">
        <div class="section-title">Skills</div>
        <div class="skills-section">
            {{ implode('  ·  ', $content['skills']) }}
        </div>
    </div>
    @endif
</body>
</html>
