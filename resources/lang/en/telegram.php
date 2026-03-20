<?php

return [
    'training_scheduled' => "Hello :client_name,\n\nYour training session has been scheduled.\n\nDate: :date\nTime: :time\nTrainer: :trainer_name\n\nPlease be ready on time.",
    'training_rescheduled' => "Hello :client_name,\n\nYour training session has been rescheduled.\n\nDate: :date\nTime: :time\nTrainer: :trainer_name\n\nPlease be ready on time." . " :reason",
    'training_cancelled' => "Hello :client_name,\n\nWe regret to inform you that your training session has been cancelled.\n\nWe apologize for any inconvenience this may cause.\n\nPlease contact our team to reschedule or for further information.\n\nThank you for your understanding." . " :reason",
    'training_on_the_way' => "Hello :client_name,\n\nYour trainer :trainer_name is on the way for your training session.\n\nEstimated arrival time: :time\n\nPlease be ready.",
    'training_started' => "Hello :client_name,\n\nYour training session has started.\n\nTrainer: :trainer_name\nStart time: :time\n\nEnjoy your training!",
    'training_completed' => "Hello :client_name,\n\nYour training session on :date has been completed.\n\nThank you for participating.",
    'onboarding_started' => "Hello :client_name,\n\nYour onboarding process has started.\n\nOur team will guide you through each step. Please stay tuned for updates.",
    'onboarding_step_completed' => "Hello :client_name,\n\nOnboarding step completed: :step_name\n\nProgress: :progress%\n\nWell done!",
    'lesson_sent' => "Hello :client_name,\n\nA new lesson has been sent to you.\n\nLesson: :lesson_name\n\nPlease review it at your earliest convenience.",
    'test_message' => "Hello :client_name,\n\nThis is a test message from the COMS system.\n\nIf you received this, the Telegram connection is working correctly.",
    'reminder' => "Hello :client_name,\n\nThis is a reminder: :message\n\nThank you.",
];
