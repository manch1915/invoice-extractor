<?php

declare(strict_types=1);

return [
    'array' => 'Поле :attribute должно быть списком.',
    'between' => [
        'numeric' => 'Значение :attribute должно быть от :min до :max.',
    ],
    'email' => 'Укажите корректный адрес почты.',
    'exists' => 'Выбранное значение :attribute не найдено.',
    'file' => 'Поле :attribute должно быть файлом.',
    'in' => 'Выбрано недопустимое значение :attribute.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'max' => [
        'array' => 'Не больше :max файлов за раз.',
        'file' => 'Файл :attribute больше :max КБ.',
        'string' => 'Не длиннее :max символов.',
    ],
    'mimes' => 'Подходят файлы :values.',
    'min' => [
        'array' => 'Выберите хотя бы :min файл.',
        'numeric' => 'Значение :attribute не меньше :min.',
    ],
    'numeric' => 'Поле :attribute должно быть числом.',
    'required' => 'Заполните поле :attribute.',
    'required_with' => 'Поле :attribute нужно вместе с :values.',
    'size' => [
        'array' => 'Поле :attribute должно содержать :size элемента.',
    ],
    'string' => 'Поле :attribute должно быть строкой.',

    'money' => 'Введите сумму, например 1234,56.',
    'document_date' => 'Введите дату в виде 05.10.2026 или 2026-10-05.',
    'tax_rate' => 'Ставка налога от 0 до 100.',
    'currency' => 'Код валюты из трёх букв: RUB, USD, EUR.',

    'attributes' => [
        'files' => 'файлы',
        'value' => 'значение',
        'email' => 'почта',
        'password' => 'пароль',
        'category_id' => 'категория',
    ],
];
