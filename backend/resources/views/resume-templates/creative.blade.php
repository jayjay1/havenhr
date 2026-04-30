<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $content['personal_info']['name'] ?? 'Resume' }}</title>
    <style>
        /* Creative Template — Colorful sidebar, icons, modern design */
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
        .page-wrapper {
            display: table;
            width: 100%;
            min-height: 100%;
        }
        .sidebar {
            display: table-cell;
            width: 35%;
            background-color: #2d3748;
            color: #e2e8f0;
            padding: 30px 20px;
            vertical-align: top;
        }
        .main-content {
            display: table-cell;
            width: 65%;
            padding: 30px 35px;
            vertical-align: top;
        }
        /* Sidebar styles */
        .sidebar-name {
            font-size: 20pt;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
            line-height: 1.2;
        }
        .sidebar-tagline {
            font-size: 9pt;
            color: #a0aec0;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .sidebar-section {
            margin-bottom: 22px;
        }
        .sidebar-section-title {
            font-size: 10pt;
            font-weight: 700;
            color: #63b3ed;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #4a5568;
        }
        .contact-item {
            font-size: 9pt;
            color: #cbd5e0;
            margin-bottom: 5px;
            padding-left: 16px;
            position: relative;
        }
        .contact-item:before {
            content: "›";
            position: absolute;
            left: 0;
            color: #63b3ed;
            font-weight: 700;
        }
        .skill-bar-container {
            margin-bottom: 6px;
        }
        .skill-name {
            font-size: 9pt;
            color: #e2e8f0;
            margin-bottom: 2px;
        }
        .skill-bar {
            height: 4px;
            background-color: #4a5568;
            border-radius: 2px;
        }
        .skill-bar-fill {
            height: 4px;
            background-color: #63b3ed;
            border-radius: 2px;
        }
        .edu-sidebar-entry {
            margin-bottom: 10px;
        }
        .edu-sidebar-degree {
            font-size: 9.5pt;
            font-weight: 600;
            color: #ffffff;
        }
        .edu-sidebar-school {
            font-size: 8.5pt;
            color: #a0aec0;
        }
        .edu-sidebar-date {
            font-size: 8pt;
            color: #718096;
        }
        /* Main content styles */
        .main-section {
            margin-bottom: 22px;
        }
        .main-section-title {
            font-size: 14pt;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
            padding-bottom: 4px;
            border-bottom: 2px solid #63b3ed;
        }
        .summary-text {
            font-size: 10pt;
            color: #4a5568;
            line-height: 1.7;
        }
        .work-entry {
            margin-bottom: 16px;
            padding-left: 14px;
            border-left: 3px solid #63b3ed;
        }
        .work-title {
            font-weight: 700;
            font-size: 11pt;
            color: #2d3748;
        }
        .work-company {
            font-size: 10pt;
            color: #63b3ed;
            font-weight: 600;
        }
        .work-date {
            font-size: 8.5pt;
            color: #a0aec0;
            margin-bottom: 4px;
        }
        .work-bullets {
            list-style-type: none;
            margin-left: 0;
            font-size: 9.5pt;
            color: #4a5568;
        }
        .work-bullets li {
            margin-bottom: 3px;
            padding-left: 12px;
            position: relative;
        }
        .work-bullets li:before {
            content: "◆";
            position: absolute;
            left: 0;
            color: #63b3ed;
            font-size: 6pt;
            top: 3px;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        {{-- Sidebar --}}
        <div class="sidebar">
            <div class="sidebar-name">{{ $content['personal_info']['name'] ?? '' }}</div>
            <div class="sidebar-tagline">Resume</div>

            {{-- Contact --}}
            <div class="sidebar-section">
                <div class="sidebar-section-title">Contact</div>
                @if(!empty($content['personal_info']['email']))
                <div class="contact-item">{{ $content['personal_info']['email'] }}</div>
                @endif
                @if(!empty($content['personal_info']['phone']))
                <div class="contact-item">{{ $content['personal_info']['phone'] }}</div>
                @endif
                @if(!empty($content['personal_info']['location']))
                <div class="contact-item">{{ $content['personal_info']['location'] }}</div>
                @endif
                @if(!empty($content['personal_info']['linkedin_url']))
                <div class="contact-item">{{ $content['personal_info']['linkedin_url'] }}</div>
                @endif
                @if(!empty($content['personal_info']['portfolio_url']))
                <div class="contact-item">{{ $content['personal_info']['portfolio_url'] }}</div>
                @endif
            </div>

            {{-- Skills --}}
            @if(!empty($content['skills']))
            <div class="sidebar-section">
                <div class="sidebar-section-title">Skills</div>
                @foreach($content['skills'] as $skill)
                <div class="skill-bar-container">
                    <div class="skill-name">{{ $skill }}</div>
                    <div class="skill-bar">
                        <div class="skill-bar-fill" style="width: 80%;"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Education --}}
            @if(!empty($content['education']))
            <div class="sidebar-section">
                <div class="sidebar-section-title">Education</div>
                @foreach($content['education'] as $edu)
                <div class="edu-sidebar-entry">
                    <div class="edu-sidebar-degree">{{ $edu['degree'] ?? '' }}{{ !empty($edu['field_of_study']) ? ' in ' . $edu['field_of_study'] : '' }}</div>
                    <div class="edu-sidebar-school">{{ $edu['institution_name'] ?? '' }}</div>
                    <div class="edu-sidebar-date">{{ $edu['start_date'] ?? '' }} — {{ $edu['end_date'] ?? 'Present' }}</div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Main Content --}}
        <div class="main-content">
            {{-- Summary --}}
            @if(!empty($content['summary']))
            <div class="main-section">
                <div class="main-section-title">About Me</div>
                <div class="summary-text">{{ $content['summary'] }}</div>
            </div>
            @endif

            {{-- Work Experience --}}
            @if(!empty($content['work_experience']))
            <div class="main-section">
                <div class="main-section-title">Experience</div>
                @foreach($content['work_experience'] as $job)
                <div class="work-entry">
                    <div class="work-title">{{ $job['job_title'] ?? '' }}</div>
                    <div class="work-company">{{ $job['company_name'] ?? '' }}</div>
                    <div class="work-date">{{ $job['start_date'] ?? '' }} — {{ $job['end_date'] ?? 'Present' }}</div>
                    @if(!empty($job['bullets']))
                    <ul class="work-bullets">
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
    </div>
</body>
</html>
