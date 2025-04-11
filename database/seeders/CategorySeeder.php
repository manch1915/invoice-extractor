<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** Account codes follow the default Xero chart of accounts. */
    private const CATEGORIES = [
        ['raw_materials', 'Raw materials', 'Сырьё и ингредиенты', '#b7791f', '310', ['мука', 'молоко', 'масло', 'сливки', 'творог', 'солод', 'отруби', 'flour', 'butter', 'milk']],
        ['packaging', 'Packaging', 'Упаковка', '#c05621', '310', ['пакет', 'коробка', 'стакан', 'плёнка', 'лента упаковочная', 'packaging', 'box']],
        ['equipment', 'Equipment and tools', 'Оборудование и инвентарь', '#2b6cb0', '710', ['tray', 'mixer', 'mat', 'basket', 'scraper', 'печь', 'миксер', 'противень']],
        ['rent', 'Rent', 'Аренда', '#6b46c1', '469', ['аренда', 'rent', 'lease']],
        ['utilities', 'Utilities', 'Коммунальные услуги', '#2c7a7b', '445', ['электроэнергия', 'водоснабжение', 'отопление', 'electricity', 'water']],
        ['telecom', 'Internet and phone', 'Связь и интернет', '#3182ce', '489', ['интернет', 'ip-адрес', 'связь', 'internet', 'phone']],
        ['software', 'Software and subscriptions', 'ПО и подписки', '#5a67d8', '485', ['plan', 'subscription', 'hosting', 'vps', 'domain', 'подписка', 'хостинг']],
        ['marketing', 'Marketing', 'Маркетинг и реклама', '#d53f8c', '400', ['ads', 'campaign', 'banner', 'menu cards', 'stickers', 'реклама', 'листовки']],
        ['logistics', 'Delivery and logistics', 'Доставка и логистика', '#dd6b20', '425', ['доставка', 'рейс', 'delivery', 'freight', 'courier']],
        ['maintenance', 'Repairs and maintenance', 'Ремонт и обслуживание', '#718096', '473', ['ремонт', 'диагностика', 'замена', 'выезд инженера', 'repair', 'service visit']],
        ['office', 'Office supplies', 'Канцелярия и офис', '#38a169', '461', ['бумага', 'картридж', 'ручка', 'папка', 'термолента', 'notebook', 'pens', 'paper']],
        ['cleaning', 'Cleaning and hygiene', 'Уборка и хозтовары', '#319795', '408', ['уборка', 'моющее', 'губки', 'салфетки', 'дезинф', 'мусора', 'cleaning']],
        ['fuel', 'Fuel and transport', 'Топливо и транспорт', '#e53e3e', '449', ['аи-95', 'аи-92', 'дт ', 'бензин', 'fuel', 'petrol']],
        ['meals', 'Meals and entertainment', 'Представительские расходы', '#ed8936', '420', ['coffee', 'croissant', 'flat white', 'lunch', 'кофе', 'обед']],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as [$code, $en, $ru, $color, $account, $keywords]) {
            Category::query()->updateOrCreate(['code' => $code], [
                'name_en' => $en,
                'name_ru' => $ru,
                'color' => $color,
                'account_code' => $account,
                'keywords' => $keywords,
            ]);
        }
    }
}
