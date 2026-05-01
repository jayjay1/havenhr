<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\PipelineStage;
use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\Resume;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'james.jayson@gmail.com')->first();
        if (!$user) {
            echo "User not found!\n";
            return;
        }

        $tenantId = $user->tenant_id;

        // Company is the tenant itself
        $company = Company::find($tenantId);
        echo "Company: {$company->name}\n";

        // Create 2 published jobs
        $job1 = JobPosting::create([
            'id' => Str::uuid(),
            'tenant_id' => $tenantId,
            'created_by' => $user->id,
            'title' => 'Senior Full Stack Developer',
            'slug' => 'senior-full-stack-developer',
            'status' => 'published',
            'department' => 'Engineering',
            'location' => 'New York, NY',
            'employment_type' => 'full-time',
            'remote_status' => 'hybrid',
            'salary_min' => 120000,
            'salary_max' => 180000,
            'salary_currency' => 'USD',
            'description' => "We are looking for a Senior Full Stack Developer to join our growing engineering team.\n\nYou will work on building and scaling our HR SaaS platform using Laravel, Next.js, and PostgreSQL.\n\nResponsibilities:\n- Design and implement new features end-to-end\n- Write clean, maintainable, and well-tested code\n- Collaborate with product and design teams\n- Mentor junior developers\n- Participate in code reviews and architecture discussions",
            'requirements' => "- 5+ years of experience with PHP/Laravel and React/Next.js\n- Strong understanding of REST APIs and database design\n- Experience with multi-tenant SaaS applications\n- Familiarity with CI/CD pipelines and cloud infrastructure (AWS)\n- Excellent communication skills",
            'benefits' => "- Competitive salary with equity\n- Health, dental, and vision insurance\n- 401k with company matching\n- Unlimited PTO\n- Remote-friendly culture\n- Annual learning budget",
            'published_at' => now(),
        ]);

        $job2 = JobPosting::create([
            'id' => Str::uuid(),
            'tenant_id' => $tenantId,
            'created_by' => $user->id,
            'title' => 'Product Designer',
            'slug' => 'product-designer',
            'status' => 'published',
            'department' => 'Design',
            'location' => 'San Francisco, CA',
            'employment_type' => 'full-time',
            'remote_status' => 'remote',
            'salary_min' => 100000,
            'salary_max' => 150000,
            'salary_currency' => 'USD',
            'description' => "Join our design team to create beautiful, intuitive experiences for HR professionals.\n\nYou will own the end-to-end design process from research to high-fidelity prototypes.",
            'requirements' => "- 3+ years of product design experience\n- Proficiency in Figma\n- Strong portfolio demonstrating UX thinking\n- Experience with design systems",
            'benefits' => "- Competitive salary\n- Full benefits package\n- Remote-first culture\n- Design conference budget",
            'published_at' => now(),
        ]);

        echo "Jobs created: {$job1->title}, {$job2->title}\n";

        // Create pipeline stages for both jobs
        $stageNames = ['Applied', 'Phone Screen', 'Technical Interview', 'Culture Fit', 'Offer', 'Rejected'];
        $stageColors = ['#3B82F6', '#8B5CF6', '#F59E0B', '#10B981', '#06B6D4', '#EF4444'];

        $job1Stages = [];
        $job2Stages = [];

        foreach ($stageNames as $i => $name) {
            $job1Stages[$name] = PipelineStage::create([
                'id' => Str::uuid(),
                'job_posting_id' => $job1->id,
                'name' => $name,
                'color' => $stageColors[$i],
                'sort_order' => $i,
            ]);

            $job2Stages[$name] = PipelineStage::create([
                'id' => Str::uuid(),
                'job_posting_id' => $job2->id,
                'name' => $name,
                'color' => $stageColors[$i],
                'sort_order' => $i,
            ]);
        }

        echo "Pipeline stages created\n";

        // Create candidates and applications for Job 1 (Senior Full Stack Developer)
        $job1Candidates = [
            ['Alice Johnson', 'alice.johnson@gmail.com', 'Applied'],
            ['Bob Smith', 'bob.smith@outlook.com', 'Applied'],
            ['Leo Jackson', 'leo.jackson@outlook.com', 'Applied'],
            ['Carol Williams', 'carol.w@yahoo.com', 'Phone Screen'],
            ['David Brown', 'david.brown@gmail.com', 'Phone Screen'],
            ['Emma Davis', 'emma.davis@hotmail.com', 'Technical Interview'],
            ['Frank Miller', 'frank.miller@gmail.com', 'Technical Interview'],
            ['Grace Wilson', 'grace.wilson@gmail.com', 'Technical Interview'],
            ['Henry Moore', 'henry.moore@outlook.com', 'Culture Fit'],
            ['Ivy Taylor', 'ivy.taylor@gmail.com', 'Culture Fit'],
            ['Jack Anderson', 'jack.anderson@yahoo.com', 'Offer'],
            ['Karen Thomas', 'karen.thomas@gmail.com', 'Rejected'],
        ];

        foreach ($job1Candidates as $c) {
            $candidate = Candidate::create([
                'id' => Str::uuid(),
                'name' => $c[0],
                'email' => $c[1],
                'password_hash' => bcrypt('Password123!'),
            ]);

            $resume = Resume::create([
                'id' => Str::uuid(),
                'candidate_id' => $candidate->id,
                'title' => $c[0] . ' Resume',
                'template_slug' => 'professional',
                'content' => json_encode(['summary' => 'Experienced developer with strong skills']),
                'is_complete' => true,
            ]);

            $stage = $job1Stages[$c[2]];
            JobApplication::create([
                'id' => Str::uuid(),
                'candidate_id' => $candidate->id,
                'job_posting_id' => $job1->id,
                'resume_id' => $resume->id,
                'pipeline_stage_id' => $stage->id,
                'resume_snapshot' => json_encode(['summary' => 'Experienced developer with strong skills']),
                'status' => 'active',
                'applied_at' => now()->subDays(rand(1, 30)),
            ]);
        }

        // Create candidates for Job 2 (Product Designer)
        $job2Candidates = [
            ['Maria Garcia', 'maria.garcia@gmail.com', 'Applied'],
            ['Nathan Lee', 'nathan.lee@outlook.com', 'Applied'],
            ['Olivia Chen', 'olivia.chen@gmail.com', 'Phone Screen'],
            ['Peter Kim', 'peter.kim@yahoo.com', 'Technical Interview'],
            ['Quinn Patel', 'quinn.patel@gmail.com', 'Offer'],
        ];

        foreach ($job2Candidates as $c) {
            $candidate = Candidate::create([
                'id' => Str::uuid(),
                'name' => $c[0],
                'email' => $c[1],
                'password_hash' => bcrypt('Password123!'),
            ]);

            $resume = Resume::create([
                'id' => Str::uuid(),
                'candidate_id' => $candidate->id,
                'title' => $c[0] . ' Resume',
                'template_slug' => 'professional',
                'content' => json_encode(['summary' => 'Creative designer with UX focus']),
                'is_complete' => true,
            ]);

            $stage = $job2Stages[$c[2]];
            JobApplication::create([
                'id' => Str::uuid(),
                'candidate_id' => $candidate->id,
                'job_posting_id' => $job2->id,
                'resume_id' => $resume->id,
                'pipeline_stage_id' => $stage->id,
                'resume_snapshot' => json_encode(['summary' => 'Creative designer with UX focus']),
                'status' => 'active',
                'applied_at' => now()->subDays(rand(1, 20)),
            ]);
        }

        echo "Created 12 candidates for Job 1, 5 candidates for Job 2\n";
        echo "Done! Sign in and go to Dashboard > Jobs to see the Kanban board.\n";
    }
}
