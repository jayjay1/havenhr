<?php

return [
    'phone_screen' => [
        'name' => 'Phone Screen Kit',
        'description' => 'Initial screening questions for phone interviews',
        'questions' => [
            ['text' => 'Tell me about your background and what interests you about this role', 'category' => 'experience', 'scoring_rubric' => null],
            ['text' => 'What are your salary expectations?', 'category' => 'experience', 'scoring_rubric' => null],
            ['text' => 'Describe a challenging project you worked on recently', 'category' => 'experience', 'scoring_rubric' => '1: Vague answer. 3: Clear example with some detail. 5: Compelling story with measurable impact'],
            ['text' => 'Why are you looking to leave your current position?', 'category' => 'behavioral', 'scoring_rubric' => null],
        ],
    ],
    'technical_interview' => [
        'name' => 'Technical Interview Kit',
        'description' => 'Technical assessment questions for engineering roles',
        'questions' => [
            ['text' => 'Describe your approach to system design for a high-traffic application', 'category' => 'technical', 'scoring_rubric' => '1: No understanding. 3: Basic concepts. 5: Expert-level design with trade-off analysis'],
            ['text' => 'How do you approach debugging a production issue?', 'category' => 'technical', 'scoring_rubric' => '1: No methodology. 3: Systematic approach. 5: Comprehensive strategy with monitoring and prevention'],
            ['text' => 'Explain a complex technical concept to a non-technical stakeholder', 'category' => 'behavioral', 'scoring_rubric' => '1: Unable to simplify. 3: Adequate explanation. 5: Clear, engaging explanation with analogies'],
            ['text' => 'How do you stay current with technology trends?', 'category' => 'experience', 'scoring_rubric' => null],
        ],
    ],
    'culture_fit' => [
        'name' => 'Culture Fit Kit',
        'description' => 'Questions to assess cultural alignment and team fit',
        'questions' => [
            ['text' => 'Describe your ideal work environment', 'category' => 'cultural', 'scoring_rubric' => null],
            ['text' => 'How do you handle disagreements with team members?', 'category' => 'behavioral', 'scoring_rubric' => '1: Avoids conflict. 3: Addresses constructively. 5: Facilitates resolution and strengthens relationships'],
            ['text' => 'Tell me about a time you received critical feedback', 'category' => 'behavioral', 'scoring_rubric' => '1: Defensive response. 3: Accepted feedback. 5: Sought feedback proactively and demonstrated growth'],
            ['text' => 'What motivates you in your work?', 'category' => 'cultural', 'scoring_rubric' => null],
        ],
    ],
    'final_round' => [
        'name' => 'Final Round Kit',
        'description' => 'Senior leadership and final assessment questions',
        'questions' => [
            ['text' => 'Where do you see yourself in 3-5 years?', 'category' => 'experience', 'scoring_rubric' => null],
            ['text' => 'What unique value would you bring to this team?', 'category' => 'cultural', 'scoring_rubric' => null],
            ['text' => 'Describe a situation where you led a team through a difficult challenge', 'category' => 'behavioral', 'scoring_rubric' => '1: No leadership example. 3: Led with some success. 5: Demonstrated exceptional leadership with clear outcomes'],
            ['text' => 'Do you have any questions for us?', 'category' => 'cultural', 'scoring_rubric' => null],
        ],
    ],
];
