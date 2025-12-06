<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/config/web.php';
new yii\web\Application($config);

use app\models\Vacancy;

echo "Добавление тестовых данных...\n\n";

$vacancies = [
    [
        'title' => 'Senior PHP Developer',
        'description' => 'Требуется опытный PHP разработчик для работы над крупными проектами. Требования: PHP 8+, Yii2/Laravel, MySQL, Git.',
        'salary' => 200000,
        'additional_fields' => json_encode([
            'company' => 'Tech Corp',
            'location' => 'Москва',
            'employment_type' => 'full-time',
            'experience' => '5+ лет'
        ]),
    ],
    [
        'title' => 'Frontend Developer (React)',
        'description' => 'Ищем frontend разработчика с опытом работы с React. Требования: React, Redux, TypeScript, HTML/CSS.',
        'salary' => 150000,
        'additional_fields' => json_encode([
            'company' => 'Startup Inc',
            'location' => 'Санкт-Петербург',
            'employment_type' => 'full-time',
            'experience' => '3+ года'
        ]),
    ],
    [
        'title' => 'DevOps Engineer',
        'description' => 'Требуется DevOps инженер для автоматизации процессов развертывания. Требования: Docker, Kubernetes, CI/CD, Linux.',
        'salary' => 180000,
        'additional_fields' => json_encode([
            'company' => 'Cloud Solutions',
            'location' => 'Удаленно',
            'employment_type' => 'full-time',
            'experience' => '4+ года'
        ]),
    ],
    [
        'title' => 'Junior Python Developer',
        'description' => 'Приглашаем начинающего Python разработчика в дружную команду. Требования: Python, Django/Flask, SQL.',
        'salary' => 80000,
        'additional_fields' => json_encode([
            'company' => 'Data Analytics',
            'location' => 'Москва',
            'employment_type' => 'full-time',
            'experience' => '1+ год'
        ]),
    ],
    [
        'title' => 'Full Stack Developer',
        'description' => 'Ищем full stack разработчика для работы над веб-приложениями. Требования: PHP/Node.js, Vue.js/React, PostgreSQL.',
        'salary' => 170000,
        'additional_fields' => json_encode([
            'company' => 'WebDev Studio',
            'location' => 'Новосибирск',
            'employment_type' => 'full-time',
            'experience' => '3+ года'
        ]),
    ],
    [
        'title' => 'QA Engineer',
        'description' => 'Требуется QA инженер для тестирования веб-приложений. Требования: опыт ручного и автоматизированного тестирования, Selenium.',
        'salary' => 120000,
        'additional_fields' => json_encode([
            'company' => 'Quality Assurance',
            'location' => 'Казань',
            'employment_type' => 'full-time',
            'experience' => '2+ года'
        ]),
    ],
    [
        'title' => 'Mobile Developer (iOS)',
        'description' => 'Разработчик мобильных приложений для iOS. Требования: Swift, SwiftUI, опыт публикации в App Store.',
        'salary' => 160000,
        'additional_fields' => json_encode([
            'company' => 'Mobile Apps',
            'location' => 'Москва',
            'employment_type' => 'full-time',
            'experience' => '3+ года'
        ]),
    ],
    [
        'title' => 'Database Administrator',
        'description' => 'Администратор баз данных для поддержки и оптимизации СУБД. Требования: PostgreSQL/MySQL, опыт репликации и бэкапов.',
        'salary' => 140000,
        'additional_fields' => json_encode([
            'company' => 'Big Data Corp',
            'location' => 'Екатеринбург',
            'employment_type' => 'full-time',
            'experience' => '3+ года'
        ]),
    ],
    [
        'title' => 'Team Lead (Backend)',
        'description' => 'Тимлид для управления командой backend разработчиков. Требования: опыт руководства, PHP/Python, архитектура ПО.',
        'salary' => 250000,
        'additional_fields' => json_encode([
            'company' => 'Enterprise Systems',
            'location' => 'Москва',
            'employment_type' => 'full-time',
            'experience' => '7+ лет'
        ]),
    ],
    [
        'title' => 'UI/UX Designer',
        'description' => 'Дизайнер интерфейсов для создания удобных и красивых веб-приложений. Требования: Figma, Adobe XD, портфолио.',
        'salary' => 130000,
        'additional_fields' => json_encode([
            'company' => 'Design Studio',
            'location' => 'Санкт-Петербург',
            'employment_type' => 'full-time',
            'experience' => '2+ года'
        ]),
    ],
    [
        'title' => 'Data Scientist',
        'description' => 'Специалист по анализу данных и машинному обучению. Требования: Python, pandas, scikit-learn, опыт с нейронными сетями.',
        'salary' => 190000,
        'additional_fields' => json_encode([
            'company' => 'AI Research',
            'location' => 'Москва',
            'employment_type' => 'full-time',
            'experience' => '4+ года'
        ]),
    ],
    [
        'title' => 'System Administrator',
        'description' => 'Системный администратор для поддержки ИТ-инфраструктуры. Требования: Linux, Windows Server, сети, опыт администрирования.',
        'salary' => 110000,
        'additional_fields' => json_encode([
            'company' => 'IT Services',
            'location' => 'Ростов-на-Дону',
            'employment_type' => 'full-time',
            'experience' => '2+ года'
        ]),
    ],
];

$count = 0;
foreach ($vacancies as $data) {
    $vacancy = new Vacancy();
    $vacancy->attributes = $data;

    if ($vacancy->save()) {
        $count++;
        echo "✓ Добавлена вакансия: {$vacancy->title}\n";
    } else {
        echo "✗ Ошибка при добавлении вакансии: {$data['title']}\n";
        print_r($vacancy->errors);
    }
}

echo "\n================================\n";
echo "Добавлено вакансий: $count из " . count($vacancies) . "\n";
echo "================================\n";
