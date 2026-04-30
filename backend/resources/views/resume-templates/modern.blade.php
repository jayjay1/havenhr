<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $content['personal_info']['name'] ?? 'Resume' }}</title>
    <style>
        /* Modern Template — Bold headers, accent colors, two-column layout */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #2d2d2d;
            padding: 0;
        }
        .header {
            background-color: #1a365d;
            color: #ffffff;
            padding: 30px 40px;
            margin-bottom: 0;
        }
        .header h1 {
            font-size: 24pt;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }
        .header .contact-info {
            font-size: 9pt;
            color: #cbd5e0;
            line-height: 1.6;
        }
        .content-wrapper {
            display: table;
            width: 100%;
        }
        .main-column {
            display: table-cell;
            width: 65%;
            padding: 25px 30px 25px 40px;
            vertical-align: top;
        }
        .side-column {
            display: table-cell;
            width: 35%;
            padding: 25px 40px 25px 20px;
            vertical-align: top;
            background-color: #f7fafc;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 13pt;
            font-weight: 700;
            color: #1a365d;
            margin-bottom: 10px;
            padding-bottom: 4px;
            border-bottom: 2px solid #3182ce;
        }
        .summary {
            font-size: 10pt;
            color: #4a5568;
            line-height: 1.6;
        }
        .entry {
            margin-bottom: 14px;
        }
        .entry-title {
            font-weight: 700;
            font-size: 11pt;
            color: #1a365d;
        }
        .entry-company {
            font-size: 10pt;
            color: #3182ce;
            font-weight: 600;
        }
        .entry-date {
            font-size: 9pt;
            color: #718096;
            margin-bottom: 4px;
        }
        .bullets {
            list-style-type: none;
            margin-left: 0;
            font-size: 10pt;
            color: #4a5568;
        }
        .bullets li {
            margin-bottom: 3px;
            padding-left: 14px;
            position: relative;
        }
        .bullets li:before {
            content: "▸";
            color: #3182ce;
            position: absolute;
            left: 0;
        }
        .skill-tag {
            display: inline-block;
            background-color: #ebf4ff;
            color: #2b6cb0;
            padding: 3px 10px;
            margin: 2px 3px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: 500;
        }
        .side-section {
            margin-bottom: 20px;
        }
        .side-section-title {
            font-size: 11pt;
            font-weight: 700;
            color: #1a365d;
            margin-bottom: 8px;
            padding-bottom: 3px;
            border-bottom: 2px solid #3182ce;
        }
        .edu-entry {
            margin-bottom: 10px;
        }
        .edu-degree {
            font-weight: 600;
            font-size: 10pt;
            color: #2d3748;
        }
        .edu-school {
            font-size: 9pt;
            color: #4a5568;
        }
        .edu-date {
            font-size: 8pt;
            color: #a0aec0;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1>{{ $content['personal_info']['name'] ?? '' }}</h1>
        <div class="contact-info">
            @if(!empty($content['personal_info']['email']))
                {{ $content['personal_info']['email'] }}
            @endif
            @if(!empty($content['personal_info']['phone']))
                &nbsp;&nbsp;|&nbsp;&nbsp;{{ $content['personal_info']['phone'] }}
            @endif
            @if(!empty($content['personal_info']['location']))
                &nbsp;&nbsp;|&nbsp;&nbsp;{{ $content['personal_info']['location'] }}
            @endif
            @if(!empty($content['personal_info']['linkedin_url']) || !empty($content['personal_info']['portfolio_url']))
                <br>
                @if(!empty($content['personal_info']['linkedin_url']))
                    {{ $content['personal_info']['linkedin_url'] }}
                @endif
                @if(!empty($content['personal_info']['portfolio_url']))
                    &nbsp;&nbsp;|&nbsp;&nbsp;{{ $content['personal_info']['portfolio_url'] }}
                @endif
            @endif
        </div>
    </div>

    <div class="content-wrapper">
        {{-- Main Column --}}
        <div class="main-column">
            {{-- Summary --}}
            @if(!empty($content['summary']))
            <div class="section">
                <div class="section-title">Profile</div>
                <div class="summary">{{ $content['summary'] }}</div>
            </div>
            @endif

            {{-- Work Experience --}}
            @if(!empty($content['work_experience']))
            <div class="section">
                <div class="section-title">Experience</div>
                @foreach($content['work_experience'] as $job)
                <div class="entry">
                    <div class="entry-title">{{ $job['job_title'] ?? '' }}</div>
                    <div class="entry-company">{{ $job['company_name'] ?? '' }}</div>
                    <div class="entry-date">{{ $job['start_date'] ?? '' }} — {{ $job['end_date'] ?? 'Present' }}</div>
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
        </div>

        {{-- Side Column --}}
        <div class="side-column">
            {{-- Skills --}}
            @if(!empty($content['skills']))
            <div class="side-section">
                <div class="side-section-title">Skills</div>
                @foreach($content['skills'] as $skill)
                <span class="skill-tag">{{ $skill }}</span>
                @endforeach
            </div>
            @endif

            {{-- Education --}}
            @if(!empty($content['education']))
            <div class="side-section">
                <div class="side-section-title">Education</div>
                @foreach($content['education'] as $edu)
                <div class="edu-entry">
                    <div class="edu-degree">{{ $edu['degree'] ?? '' }}{{ !empty($edu['field_of_study']) ? ' in ' . $edu['field_of_study'] : '' }}</div>
                    <div class="edu-school">{{ $edu['institution_name'] ?? '' }}</div>
                    <div class="edu-date">{{ $edu['start_date'] ?? '' }} — {{ $edu['end_date'] ?? 'Present' }}</div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</body>
</html>
