"""Fictional companies and purchases for the demo set. Any match with real businesses is accidental."""

from dataclasses import dataclass, field
from datetime import date
from decimal import Decimal


def inn10(prefix: str) -> str:
    digits = [int(c) for c in prefix[:9]]
    weights = [2, 4, 10, 3, 5, 9, 4, 6, 8]
    check = sum(d * w for d, w in zip(digits, weights, strict=True)) % 11 % 10
    return prefix[:9] + str(check)


def inn12(prefix: str) -> str:
    digits = [int(c) for c in prefix[:10]]
    w11 = [7, 2, 4, 10, 3, 5, 9, 4, 6, 8]
    n11 = sum(d * w for d, w in zip(digits, w11, strict=True)) % 11 % 10
    digits.append(n11)
    w12 = [3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8]
    n12 = sum(d * w for d, w in zip(digits, w12, strict=True)) % 11 % 10
    return prefix[:10] + str(n11) + str(n12)


@dataclass(frozen=True)
class Party:
    name: str
    tax_id: str
    address: str
    bank: str = ""
    account: str = ""
    phone: str = ""
    email: str = ""
    kpp: str = ""


BUYER_RU = Party(
    name="ООО «Тёплый хлеб»",
    tax_id=inn10("772849106"),
    kpp="772801001",
    address="117418, г. Москва, ул. Профсоюзная, д. 56, пом. 3",
    phone="+7 495 128-44-17",
)
BUYER_EN = Party(
    name="Teply Khleb LLC",
    tax_id="",
    address="56 Profsoyuznaya St, office 3, Moscow 117418, Russia",
    email="accounts@teplykhleb.ru",
)

V = {
    "melnitsa": Party(
        "ООО «Мельница Север»",
        inn10("781345672"),
        "196084, г. Санкт-Петербург, Московский пр-т, д. 112, лит. Б",
        "ПАО «Балтийский Банк»",
        "40702810455080012345",
        "+7 812 640-21-90",
        kpp="781001001",
    ),
    "kanc": Party(
        "ООО «КанцМаркет»",
        inn10("771905338"),
        "127015, г. Москва, ул. Бутырская, д. 4, стр. 2",
        "АО «Банк Развития Столицы»",
        "40702810938000045671",
        "+7 495 790-11-02",
        kpp="771401001",
    ),
    "energo": Party(
        "АО «Энергосбыт Центр»",
        inn10("772634019"),
        "115114, г. Москва, Дербеневская наб., д. 7",
        "ПАО «Сбережения»",
        "40702810100020000143",
        "+7 495 981-00-00",
        kpp="772501001",
    ),
    "clean": Party(
        "ООО «Клининг Про»",
        inn10("773018847"),
        "119334, г. Москва, Ленинский пр-т, д. 38А",
        "АО «Точка Роста»",
        "40702810820000077120",
        "+7 926 455-12-80",
        kpp="773601001",
    ),
    "sokolova": Party(
        "ИП Соколова Анна Викторовна",
        inn12("5003127745"),
        "142718, Московская обл., пос. Битца, ул. Садовая, д. 9",
        "АО «Точка Роста»",
        "40802810820000011983",
        "+7 916 302-77-41",
    ),
    "moloko": Party(
        "ООО «Молочный двор»",
        inn10("504723918"),
        "141281, Московская обл., г. Ивантеевка, Заводская ул., д. 2",
        "ПАО «Региональный Банк»",
        "40702810740020004412",
        "+7 495 993-50-14",
        kpp="501601001",
    ),
    "techservice": Party(
        "ООО «ТехСервис Плюс»",
        inn10("772203481"),
        "109316, г. Москва, Остаповский пр-д, д. 5, стр. 1",
        "АО «Банк Развития Столицы»",
        "40702810638000088214",
        "+7 495 225-60-33",
        kpp="772301001",
    ),
    "telecom": Party(
        "ООО «Связь-Телеком»",
        inn10("770491755"),
        "123100, г. Москва, Пресненская наб., д. 10, оф. 412",
        "ПАО «Сбережения»",
        "40702810300020071190",
        "8 800 550-14-14",
        kpp="770301001",
    ),
    "arenda": Party(
        "ООО «Арендный центр Профсоюзный»",
        inn10("772741063"),
        "117418, г. Москва, ул. Профсоюзная, д. 56",
        "ПАО «Балтийский Банк»",
        "40702810755080033110",
        "+7 495 330-44-01",
        kpp="772701001",
    ),
    "dostavka": Party(
        "ООО «Быстрая доставка»",
        inn10("773415280"),
        "125424, г. Москва, Волоколамское ш., д. 73",
        "АО «Точка Роста»",
        "40702810020000054478",
        "+7 495 106-10-10",
        kpp="773301001",
    ),
    "hyper": Party("ООО «Гипермаркет Восток»", inn10("772090143"), "г. Москва, ул. Профсоюзная, д. 61"),
    "azs": Party("ООО «Нефтьсервис-М»", inn10("771522964"), "АЗС № 214, г. Москва, Калужское ш., 21 км"),
    "upak_bad": Party(
        "ООО «УпакСнаб»",
        "7728510344",
        "115230, г. Москва, Варшавское ш., д. 42, стр. 7",
        "ПАО «Региональный Банк»",
        "40702810540020009910",
        "+7 495 640-00-71",
        kpp="772601001",
    ),
    # EN suppliers
    "bakeware": Party(
        "Bakeware Direct Ltd",
        "GB 284 6175 39",
        "Unit 7, Riverside Trade Park, Leeds LS11 5DF, United Kingdom",
        email="orders@bakewaredirect.co.uk",
        phone="+44 113 496 0182",
    ),
    "hoffmann": Party(
        "Hoffmann Küchentechnik GmbH",
        "DE 318 452 907",
        "Industriestraße 14, 70565 Stuttgart, Germany",
        email="rechnung@hoffmann-kt.de",
        phone="+49 711 958 220",
    ),
    "canvasly": Party(
        "Canvasly Inc.",
        "EIN 84-2219573",
        "548 Market St, Suite 320, San Francisco, CA 94104, USA",
        email="billing@canvasly.app",
    ),
    "cloudnest": Party(
        "Cloudnest Hosting LLC",
        "EIN 87-1046628",
        "1209 Orange St, Wilmington, DE 19801, USA",
        email="invoices@cloudnest.io",
    ),
    "printhouse": Party(
        "PrintHouse Studio Ltd",
        "GB 192 4408 61",
        "14 Brick Lane, London E1 6RF, United Kingdom",
        email="hello@printhouse.studio",
    ),
    "adreach": Party(
        "AdReach Media Ltd",
        "GB 377 9021 45",
        "2 Kingdom St, London W2 6BD, United Kingdom",
        email="billing@adreach.media",
    ),
    "cafe": Party("Corner Bean Cafe", "", "201 Bleecker St, New York, NY 10012"),
    "paperco": Party("Paper & Co Stationers", "GB 305 6610 78", "88 Upper Street, London N1 0NP"),
}


@dataclass(frozen=True)
class Item:
    name: str
    qty: Decimal
    unit: str
    price: Decimal

    @property
    def amount(self) -> Decimal:
        return (self.qty * self.price).quantize(Decimal("0.01"))


def item(name: str, qty: str, unit: str, price: str) -> Item:
    return Item(name, Decimal(qty.replace(" ", "")), unit, Decimal(price.replace(" ", "")))


@dataclass
class Doc:
    slug: str
    template: str  # ru_invoice, ru_waybill, ru_act, ru_receipt, en_invoice, en_receipt
    vendor: str
    number: str
    issued: date
    items: list[Item]
    currency: str = "RUB"
    vat_rate: Decimal = Decimal(20)
    vat_included: bool = True  # RU style: prices include VAT, "в т.ч. НДС"
    due: date | None = None
    quality: str = "clean"  # clean, scan, photo, blurry
    category: str = ""
    eu_numbers: bool = False
    # deliberate mistakes in the printed document
    total_override: Decimal | None = None
    vat_override: Decimal | None = None
    notes: list[str] = field(default_factory=list)


D = date
I = item  # noqa: E741

DOCS: list[Doc] = [
    # --- Raw materials ---
    Doc(
        "melnitsa-0712",
        "ru_invoice",
        "melnitsa",
        "1184",
        D(2026, 7, 12),
        [
            I("Мука пшеничная хлебопекарная в/с, мешок 50 кг", "40", "шт", "1 890.00"),
            I("Мука ржаная обдирная, мешок 50 кг", "12", "шт", "1 640.00"),
            I("Солод ржаной ферментированный, 25 кг", "2", "шт", "3 150.00"),
        ],
        vat_rate=Decimal(10),
        due=D(2026, 7, 19),
        category="raw_materials",
    ),
    Doc(
        "melnitsa-0809",
        "ru_waybill",
        "melnitsa",
        "1302",
        D(2026, 8, 9),
        [
            I("Мука пшеничная хлебопекарная в/с, мешок 50 кг", "45", "шт", "1 890.00"),
            I("Мука цельнозерновая, мешок 25 кг", "10", "шт", "1 420.00"),
        ],
        vat_rate=Decimal(10),
        quality="scan",
        category="raw_materials",
    ),
    Doc(
        "melnitsa-0914",
        "ru_invoice",
        "melnitsa",
        "1455",
        D(2026, 9, 14),
        [
            I("Мука пшеничная хлебопекарная в/с, мешок 50 кг", "50", "шт", "1 940.00"),
            I("Мука ржаная обдирная, мешок 50 кг", "15", "шт", "1 680.00"),
            I("Отруби пшеничные, мешок 20 кг", "4", "шт", "610.00"),
        ],
        vat_rate=Decimal(10),
        due=D(2026, 9, 21),
        quality="photo",
        category="raw_materials",
    ),
    Doc(
        "melnitsa-1012",
        "ru_invoice",
        "melnitsa",
        "1597",
        D(2026, 10, 12),
        [
            I("Мука пшеничная хлебопекарная в/с, мешок 50 кг", "48", "шт", "1 940.00"),
            I("Солод ржаной ферментированный, 25 кг", "3", "шт", "3 150.00"),
        ],
        vat_rate=Decimal(10),
        due=D(2026, 10, 19),
        category="raw_materials",
    ),
    Doc(
        "moloko-0721",
        "ru_waybill",
        "moloko",
        "МД-3381",
        D(2026, 7, 21),
        [
            I("Молоко питьевое 3,2%, 1 л", "120", "шт", "78.50"),
            I("Сливки 33%, 1 л", "24", "шт", "389.00"),
            I("Масло сливочное 82,5%, 5 кг", "6", "шт", "4 120.00"),
        ],
        vat_rate=Decimal(10),
        category="raw_materials",
    ),
    Doc(
        "moloko-0825",
        "ru_invoice",
        "moloko",
        "МД-3520",
        D(2026, 8, 25),
        [
            I("Молоко питьевое 3,2%, 1 л", "140", "шт", "78.50"),
            I("Масло сливочное 82,5%, 5 кг", "8", "шт", "4 120.00"),
            I("Творог 9%, 5 кг", "4", "шт", "1 980.00"),
        ],
        vat_rate=Decimal(10),
        due=D(2026, 9, 1),
        quality="scan",
        category="raw_materials",
        total_override=Decimal("53750.00"),
        notes=["printed total does not match the lines"],
    ),
    Doc(
        "moloko-1003",
        "ru_invoice",
        "moloko",
        "МД-3697",
        D(2026, 10, 3),
        [
            I("Молоко питьевое 3,2%, 1 л", "150", "шт", "81.00"),
            I("Сливки 33%, 1 л", "30", "шт", "395.00"),
            I("Масло сливочное 82,5%, 5 кг", "7", "шт", "4 190.00"),
        ],
        vat_rate=Decimal(10),
        due=D(2026, 10, 10),
        category="raw_materials",
    ),
    # --- Packaging ---
    Doc(
        "sokolova-0803",
        "ru_invoice",
        "sokolova",
        "47",
        D(2026, 8, 3),
        [
            I("Пакет крафт с окном 250×120 мм", "2000", "шт", "6.40"),
            I("Коробка для торта 300×300 мм", "300", "шт", "48.00"),
            I("Лента упаковочная с логотипом, 50 м", "10", "шт", "320.00"),
        ],
        vat_rate=Decimal(0),
        due=D(2026, 8, 10),
        category="packaging",
        notes=["sole trader, no VAT"],
    ),
    Doc(
        "sokolova-1001",
        "ru_invoice",
        "sokolova",
        "63",
        D(2026, 10, 1),
        [
            I("Пакет крафт с окном 250×120 мм", "3000", "шт", "6.40"),
            I("Стакан бумажный 350 мл с крышкой", "1500", "шт", "9.20"),
        ],
        vat_rate=Decimal(0),
        due=D(2026, 10, 8),
        quality="photo",
        category="packaging",
    ),
    Doc(
        "upaksnab-0918",
        "ru_invoice",
        "upak_bad",
        "УС-0912",
        D(2026, 9, 18),
        [
            I("Плёнка стретч пищевая 450 мм × 300 м", "12", "шт", "690.00"),
            I("Перчатки нитриловые, уп. 100 шт", "10", "уп", "540.00"),
        ],
        due=D(2026, 9, 25),
        category="packaging",
        notes=["vendor INN has a wrong check digit"],
    ),
    # --- Office ---
    Doc(
        "kanc-0715",
        "ru_invoice",
        "kanc",
        "КМ-20417",
        D(2026, 7, 15),
        [
            I("Бумага офисная А4, 500 л.", "10", "уп", "389.00"),
            I("Картридж лазерный совместимый CF259X", "2", "шт", "2 450.00"),
            I("Ручка шариковая синяя", "30", "шт", "24.00"),
        ],
        due=D(2026, 7, 22),
        category="office",
    ),
    Doc(
        "kanc-0929",
        "ru_invoice",
        "kanc",
        "КМ-21688",
        D(2026, 9, 29),
        [
            I("Бумага офисная А4, 500 л.", "8", "уп", "399.00"),
            I("Термолента для кассы 57 мм", "40", "шт", "38.00"),
            I("Папка-регистратор 75 мм", "6", "шт", "215.00"),
        ],
        due=D(2026, 10, 6),
        category="office",
    ),
    Doc(
        "kanc-0929-copy",
        "ru_invoice",
        "kanc",
        "КМ-21688",
        D(2026, 9, 29),
        [
            I("Бумага офисная А4, 500 л.", "8", "уп", "399.00"),
            I("Термолента для кассы 57 мм", "40", "шт", "38.00"),
            I("Папка-регистратор 75 мм", "6", "шт", "215.00"),
        ],
        due=D(2026, 10, 6),
        quality="scan",
        category="office",
        notes=["same invoice sent again as a scan"],
    ),
    # --- Utilities, telecom, rent ---
    Doc(
        "energo-07",
        "ru_invoice",
        "energo",
        "ЭС-07/58812",
        D(2026, 7, 31),
        [
            I("Электроэнергия за июль 2026, кВт·ч", "6420", "кВт·ч", "8.94"),
        ],
        due=D(2026, 8, 15),
        category="utilities",
    ),
    Doc(
        "energo-08",
        "ru_invoice",
        "energo",
        "ЭС-08/60174",
        D(2026, 8, 31),
        [
            I("Электроэнергия за август 2026, кВт·ч", "6810", "кВт·ч", "8.94"),
        ],
        due=D(2026, 9, 15),
        quality="scan",
        category="utilities",
    ),
    Doc(
        "energo-09",
        "ru_invoice",
        "energo",
        "ЭС-09/61533",
        D(2026, 9, 30),
        [
            I("Электроэнергия за сентябрь 2026, кВт·ч", "6590", "кВт·ч", "9.12"),
        ],
        due=D(2026, 10, 15),
        category="utilities",
    ),
    Doc(
        "telecom-08",
        "ru_act",
        "telecom",
        "СТ-0826",
        D(2026, 8, 31),
        [
            I("Доступ в интернет 300 Мбит/с, август 2026", "1", "мес", "3 900.00"),
            I("Аренда статического IP-адреса", "1", "мес", "250.00"),
        ],
        category="telecom",
    ),
    Doc(
        "telecom-09",
        "ru_act",
        "telecom",
        "СТ-0943",
        D(2026, 9, 30),
        [
            I("Доступ в интернет 300 Мбит/с, сентябрь 2026", "1", "мес", "3 900.00"),
            I("Аренда статического IP-адреса", "1", "мес", "250.00"),
        ],
        quality="photo",
        category="telecom",
    ),
    Doc(
        "arenda-08",
        "ru_invoice",
        "arenda",
        "А-0812",
        D(2026, 8, 1),
        [
            I("Аренда нежилого помещения 86 м², август 2026", "1", "мес", "215 000.00"),
        ],
        due=D(2026, 8, 5),
        category="rent",
    ),
    Doc(
        "arenda-09",
        "ru_invoice",
        "arenda",
        "А-0913",
        D(2026, 9, 1),
        [
            I("Аренда нежилого помещения 86 м², сентябрь 2026", "1", "мес", "215 000.00"),
        ],
        due=D(2026, 9, 5),
        category="rent",
    ),
    Doc(
        "arenda-10",
        "ru_invoice",
        "arenda",
        "А-1009",
        D(2026, 10, 1),
        [
            I("Аренда нежилого помещения 86 м², октябрь 2026", "1", "мес", "215 000.00"),
            I("Компенсация коммунальных платежей за сентябрь", "1", "усл", "18 640.00"),
        ],
        due=D(2026, 10, 5),
        quality="scan",
        category="rent",
        vat_override=Decimal("46728.00"),
        notes=["VAT charged as 20% of the gross total instead of 20/120"],
    ),
    # --- Services ---
    Doc(
        "clean-08",
        "ru_act",
        "clean",
        "КП-1408",
        D(2026, 8, 31),
        [
            I("Ежедневная уборка производственного цеха, август", "1", "усл", "42 000.00"),
            I("Мойка витрин и окон", "2", "усл", "3 500.00"),
        ],
        category="cleaning",
    ),
    Doc(
        "clean-09",
        "ru_act",
        "clean",
        "КП-1532",
        D(2026, 9, 30),
        [
            I("Ежедневная уборка производственного цеха, сентябрь", "1", "усл", "42 000.00"),
            I("Дезинсекционная обработка помещений", "1", "усл", "7 800.00"),
        ],
        quality="scan",
        category="cleaning",
    ),
    Doc(
        "techservice-0904",
        "ru_act",
        "techservice",
        "ТС-388",
        D(2026, 9, 4),
        [
            I("Диагностика ротационной печи", "1", "усл", "4 500.00"),
            I("Замена ТЭНа 6 кВт с работой", "2", "шт", "9 870.00"),
            I("Выезд инженера", "1", "усл", "2 000.00"),
        ],
        category="maintenance",
    ),
    Doc(
        "dostavka-0830",
        "ru_invoice",
        "dostavka",
        "БД-77412",
        D(2026, 8, 30),
        [
            I("Доставка продукции по Москве, август (рейсы)", "62", "рейс", "850.00"),
        ],
        due=D(2026, 9, 6),
        category="logistics",
    ),
    Doc(
        "dostavka-1015",
        "ru_invoice",
        "dostavka",
        "БД-79055",
        D(2027, 10, 15),
        [
            I("Доставка продукции по Москве, 1–15 октября (рейсы)", "31", "рейс", "850.00"),
        ],
        due=D(2026, 10, 22),
        category="logistics",
        notes=["issue date typed as 2027"],
    ),
    # --- RU receipts ---
    Doc(
        "hyper-0811",
        "ru_receipt",
        "hyper",
        "4417",
        D(2026, 8, 11),
        [
            I("Средство моющее для посуды 5 л", "2", "шт", "489.90"),
            I("Губки для посуды, 10 шт", "3", "уп", "129.00"),
            I("Пакеты для мусора 120 л", "2", "уп", "219.99"),
        ],
        quality="photo",
        category="cleaning",
    ),
    Doc(
        "hyper-1002",
        "ru_receipt",
        "hyper",
        "5180",
        D(2026, 10, 2),
        [
            I("Салфетки бумажные, 400 шт", "4", "уп", "149.00"),
            I("Средство дезинфицирующее 1 л", "2", "шт", "356.00"),
        ],
        quality="clean",
        category="cleaning",
    ),
    Doc(
        "azs-0907",
        "ru_receipt",
        "azs",
        "0912",
        D(2026, 9, 7),
        [
            I("АИ-95", "38.42", "л", "63.85"),
        ],
        quality="photo",
        category="fuel",
    ),
    Doc(
        "azs-1014",
        "ru_receipt",
        "azs",
        "1347",
        D(2026, 10, 14),
        [
            I("ДТ Евро", "52.10", "л", "71.40"),
        ],
        quality="blurry",
        category="fuel",
    ),
    # --- EN invoices ---
    Doc(
        "bakeware-0728",
        "en_invoice",
        "bakeware",
        "BD-24-1187",
        D(2026, 7, 28),
        [
            I("Perforated baguette tray, 5 channels", "12", "pcs", "38.50"),
            I("Silicone baking mat 600x400 mm", "20", "pcs", "14.90"),
            I("Bread proofing basket, round 22 cm", "30", "pcs", "11.25"),
        ],
        currency="GBP",
        vat_included=False,
        due=D(2026, 8, 27),
        category="equipment",
    ),
    Doc(
        "bakeware-0921",
        "en_invoice",
        "bakeware",
        "BD-24-1342",
        D(2026, 9, 21),
        [
            I("Perforated baguette tray, 5 channels", "6", "pcs", "38.50"),
            I("Dough scraper, stainless steel", "25", "pcs", "4.80"),
        ],
        currency="GBP",
        vat_included=False,
        due=D(2026, 10, 21),
        quality="scan",
        category="equipment",
    ),
    Doc(
        "hoffmann-0818",
        "en_invoice",
        "hoffmann",
        "RE-2026-0418",
        D(2026, 8, 18),
        [
            I("Spiral dough mixer SM-40, 40 l bowl", "1", "pcs", "4890.00"),
            I("Freight and insurance to Moscow", "1", "svc", "640.00"),
        ],
        currency="EUR",
        vat_rate=Decimal(0),
        vat_included=False,
        eu_numbers=True,
        due=D(2026, 9, 17),
        category="equipment",
        notes=["export, zero-rated"],
    ),
    Doc(
        "canvasly-07",
        "en_invoice",
        "canvasly",
        "CNV-58201",
        D(2026, 7, 9),
        [
            I("Canvasly Team plan, 3 seats, monthly", "3", "seat", "15.00"),
        ],
        currency="USD",
        vat_rate=Decimal(0),
        vat_included=False,
        category="software",
    ),
    Doc(
        "canvasly-08",
        "en_invoice",
        "canvasly",
        "CNV-61744",
        D(2026, 8, 9),
        [
            I("Canvasly Team plan, 3 seats, monthly", "3", "seat", "15.00"),
        ],
        currency="USD",
        vat_rate=Decimal(0),
        vat_included=False,
        category="software",
    ),
    Doc(
        "canvasly-09",
        "en_invoice",
        "canvasly",
        "CNV-65310",
        D(2026, 9, 9),
        [
            I("Canvasly Team plan, 4 seats, monthly", "4", "seat", "15.00"),
        ],
        currency="USD",
        vat_rate=Decimal(0),
        vat_included=False,
        quality="photo",
        category="software",
    ),
    Doc(
        "cloudnest-0901",
        "en_invoice",
        "cloudnest",
        "INV-2026-09-3318",
        D(2026, 9, 1),
        [
            I("VPS Standard 4 vCPU / 8 GB, September", "1", "mo", "48.00"),
            I("Managed backups", "1", "mo", "9.60"),
            I("Domain renewal teplykhleb.ru", "1", "yr", "18.00"),
        ],
        currency="USD",
        vat_rate=Decimal(0),
        vat_included=False,
        category="software",
    ),
    Doc(
        "printhouse-0905",
        "en_invoice",
        "printhouse",
        "PH-0931",
        D(2026, 9, 5),
        [
            I("Menu cards A4, 350gsm, matte lamination", "200", "pcs", "1.85"),
            I("Paper bags with logo print, setup", "1", "job", "120.00"),
            I("Window stickers, die-cut", "6", "pcs", "14.00"),
        ],
        currency="GBP",
        vat_included=False,
        due=D(2026, 9, 19),
        category="marketing",
    ),
    Doc(
        "adreach-0930",
        "en_invoice",
        "adreach",
        "AR-112094",
        D(2026, 9, 30),
        [
            I("Local search ads, September campaign", "1", "mo", "420.00"),
            I("Creative set: 6 banners", "1", "set", "180.00"),
        ],
        currency="GBP",
        vat_included=False,
        due=D(2026, 10, 14),
        quality="scan",
        category="marketing",
    ),
    # --- EN receipts ---
    Doc(
        "cafe-0916",
        "en_receipt",
        "cafe",
        "0287",
        D(2026, 9, 16),
        [
            I("Flat white", "2", "ea", "4.75"),
            I("Almond croissant", "2", "ea", "4.25"),
            I("Sparkling water", "1", "ea", "3.00"),
        ],
        currency="USD",
        vat_rate=Decimal("8.875"),
        vat_included=False,
        quality="photo",
        category="meals",
    ),
    Doc(
        "paperco-1008",
        "en_receipt",
        "paperco",
        "5521",
        D(2026, 10, 8),
        [
            I("Notebook A5 dotted", "3", "ea", "6.50"),
            I("Gel pens, pack of 10", "2", "ea", "8.99"),
        ],
        currency="GBP",
        quality="clean",
        category="office",
    ),
]
