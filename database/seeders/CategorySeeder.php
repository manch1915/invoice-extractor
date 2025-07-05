<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** Xero codes follow its default chart of accounts; 1C accounts follow the Russian chart (план счетов). */
    private const CATEGORIES = [
        ['raw_materials', 'Raw materials', 'Сырьё и ингредиенты', '#b7791f', '310', '10.01', ['мука', 'молоко', 'масло', 'сливки', 'творог', 'солод', 'отруби', 'flour', 'butter', 'milk']],
        ['packaging', 'Packaging', 'Упаковка', '#c05621', '310', '10.01', ['пакет', 'коробка', 'стакан', 'плёнка', 'лента упаковочная', 'packaging', 'box']],
        ['equipment', 'Equipment and tools', 'Оборудование и инвентарь', '#2b6cb0', '710', '10.09', ['tray', 'mixer', 'mat', 'basket', 'scraper', 'печь', 'миксер', 'противень']],
        ['rent', 'Rent', 'Аренда', '#6b46c1', '469', '26', ['аренда', 'rent', 'lease']],
        ['utilities', 'Utilities', 'Коммунальные услуги', '#2c7a7b', '445', '26', ['электроэнергия', 'водоснабжение', 'отопление', 'electricity', 'water']],
        ['telecom', 'Internet and phone', 'Связь и интернет', '#3182ce', '489', '26', ['интернет', 'ip-адрес', 'связь', 'internet', 'phone']],
        ['software', 'Software and subscriptions', 'ПО и подписки', '#5a67d8', '485', '26', ['plan', 'subscription', 'hosting', 'vps', 'domain', 'подписка', 'хостинг']],
        ['marketing', 'Marketing', 'Маркетинг и реклама', '#d53f8c', '400', '44.01', ['ads', 'campaign', 'banner', 'menu cards', 'stickers', 'реклама', 'листовки']],
        ['logistics', 'Delivery and logistics', 'Доставка и логистика', '#dd6b20', '425', '44.01', ['доставка', 'рейс', 'delivery', 'freight', 'courier']],
        ['maintenance', 'Repairs and maintenance', 'Ремонт и обслуживание', '#718096', '473', '25', ['ремонт', 'диагностика', 'замена', 'выезд инженера', 'repair', 'service visit']],
        ['office', 'Office supplies', 'Канцелярия и офис', '#38a169', '461', '26', ['бумага', 'картридж', 'ручка', 'папка', 'термолента', 'notebook', 'pens', 'paper']],
        ['cleaning', 'Cleaning and hygiene', 'Уборка и хозтовары', '#319795', '408', '26', ['уборка', 'моющее', 'губки', 'салфетки', 'дезинф', 'мусора', 'cleaning']],
        ['fuel', 'Fuel and transport', 'Топливо и транспорт', '#e53e3e', '449', '26', ['аи-95', 'аи-92', 'дт ', 'бензин', 'fuel', 'petrol']],
        ['meals', 'Meals and entertainment', 'Представительские расходы', '#ed8936', '420', '91.02', ['coffee', 'croissant', 'flat white', 'lunch', 'кофе', 'обед']],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as [$code, $en, $ru, $color, $account, $account1c, $keywords]) {
            Category::query()->updateOrCreate(['code' => $code], [
                'name_en' => $en,
                'name_ru' => $ru,
                'color' => $color,
                'account_code' => $account,
                'account_1c' => $account1c,
                'keywords' => $keywords,
            ]);
        }
    }
}
