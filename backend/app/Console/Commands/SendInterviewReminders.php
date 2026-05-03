<?php

namespace App\Console\Commands;

use App\Services\InterviewService;
use Illuminate\Console\Command;

class SendInterviewReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interviews:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send interview reminder emails to candidates and interviewers';

    /**
     * Execute the console command.
     */
    public function handle(InterviewService $service): int
    {
        $service->sendDueReminders();

        $this->info('Interview reminders sent successfully.');

        return Command::SUCCESS;
    }
}
