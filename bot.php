<?php

/*
    Какие требования у данного бота?

    Для работы бота нужен домен и установленный на нем SSL-сертификат.
    Потому что работает бот через веб-хуки.
    Существуют бесплатные сертификаты, выдаваемые на 90 дней,
    и есть программа certbot, которая умеет их автоматом обновлять когда пришло время.

    Так же нужна dash-нода и 36+ гигабайт места на диске под блокчейн.
    Это полный кошелек, скачать его нужно по ссылке:
    https://github.com/dashpay/dash/releases/download/v18.1.0/dashcore-18.1.0-x86_64-linux-gnu.tar.gz
    Распаковать командой tar -xzf dashcore-18.1.0-x86_64-linux-gnu.tar.gz
    Перейти в папку dashcore-18.1.0/bin/
    и запустить командой ./dashd --usehd, чтобы сразу создать правильный HD-кошелек.
    Опция --usehd нужна только для первого запуска, пока кошелек еще не создан.
    Последующие запуски можно делать без нее.
    После этого в соседнем терминале, перейдя в папку dashcore-18.1.0/bin/
    получите seed-фразу и ключ командой:
    ./dash-cli dumphdinfo
    Эта информация понадобится для восстановления кошелька.
    Восстанавливать нужно командой
    ./dashd --usehd --mnemonic="тут ваши секретные слова"
    предварительно удалив ~/.dashcore/wallets/wallet.dat
    Чтобы остановить ноду, выполните в соседнем терминале:
    ./dash-cli stop
    После остановки в файл ~/.dashcore/dash.conf впишите две строки
    rpcuser=user1
    rpcpassword=password1
    VPS c RAM=6GB и swap=2GB работает и нода не вылетает.
    Без swap ее прибивало.
    После полной синхронизации перезапустите ноду чтобы освободить сожранную память.
    Если в процессе работы нода будет убита, то запускайте переиндексацию:
    ./dashd -reindex
    Для того чтобы нода постоянно работала, даже когда вы отключитесь от терминала,
    воспользуйтесь утилитой screen, запустите ее командой screen -S dash
    После этого запустите ноду в рабочее состояние:
    ./dashd -instantsendnotify="php /путь/к/нашему/боту/bot.php %s" -walletnotify="php /путь/к/нашему/боту/bot.php %s"
    Нода будет непосредственно уведомлять нашего бота о поступающих транзакциях.
    Но тут есть нюанс. Транзакция может быть отправлена без InstantSend и у нее будет признак 'trusted' => false
    Отключитесь от консоли комбинацией Ctrl+A d
    Последующее подключение к screen делайте командой screen -r dash
    А посмотреть список сессий можно командой screen -ls
    Как потом извлечь монеты из ноды и отправить их на другой кошелек?
    ./dash-cli getwalletinfo
    отобразит баланс. Далее выполняем команду
    ./dash-cli sendtoaddress "тут_внешний_адрес" 0.0099 "" "" true true
    Параметр после адреса это сумма, целиком пишем все что имеем,
    затем в кавычках два ненужных нам комментария для чего-то там,
    и последние true true обозначают вычесть комиссию из общей суммы и использовать инстант сенд.
    Подробнее можно узнать командой
    ./dash-cli help sendtoaddress



    Как создать бота?

    В Телеграм ищем юзера @BotFather и создаем ногово бота командой /newbot.
    Сперва указываем имя, а потом юзернейм. Юзернейм должен быть уникален и поэтому придется поподбирать.
    Впоследствии можно сменить имя боту командой /setname, чтобы оно совпадало с юзернеймом.
    Получаем токен, создаем файл config.php и вписываем его туда:
    <?php
    $bot = array (
        "token" => "1234567890:7PqFMAFHA_ebTJ35Q1ZIZyZCtVARfoWapas",
        "dashd_url" => "http://127.0.0.1/",
        "dashd_port" => "9998",
        "rpcuser" => "user1",
        "rpcpassword" => "password1",
        "resend_to_address" => "", // адрес для пересылки с ноды на кошелек
    );



    Как установить бота?

    Зайдите на свой сайт по адресу https://bot.site.ru/bot.php?cmd=install
    Для удаления хука зайдите по адресу https://bot.site.ru/bot.php?cmd=uninstall

*/


// Настройки бота
require( __DIR__ . "/config.php" );


// Функция для работы с Telegram API
function telegram( $cmd, $data = [] ) {
    global $bot;
    $curl = curl_init();
    curl_setopt_array( $curl, [
        CURLOPT_URL => "https://api.telegram.org/bot{$bot['token']}/{$cmd}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_POSTFIELDS => http_build_query( $data ),
    ] );
    $resp = curl_exec( $curl );
    curl_close( $curl );
    return json_decode( $resp, true );
}


// Функция для работы с Dash RPC
function rpc( $data = [] ) {
    global $bot;
    $data["jsonrpc"] = "1.0";
    $data["id"] = microtime();
    $curl = curl_init();
    $json = json_encode( $data );
    curl_setopt_array( $curl, [
        CURLOPT_URL => $bot["dashd_url"],
        CURLOPT_PORT => $bot["dashd_port"],
        CURLOPT_USERPWD => "{$bot['rpcuser']}:{$bot['rpcpassword']}",
        CURLOPT_HEADER => 0,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 1,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => [ "Content-type: text/plain", "Content-length: " . strlen( $json ) ],
    ] );
    $resp = curl_exec( $curl );
    curl_close( $curl );
    return json_decode( $resp, true );
}


// Логика дяльнейшего кода делится на три части.
// Информация может поступать к боту из трех мест.
// Во-первых, это две команды через адресную строку браузера install и uninstall, через $_GET.
// Во-вторых, уведомления от Ноды, о входящих и исходящих монетах, через параметр командной строки $argv.
// В-третьих, от Телеграма, через php://input.
// Все три обработчика расположены друг за другом.
// Если один из них не находит для себя информацию, то исполнение продолжается и следующий ищет свое.
// Это такая вот подсказочка, чтобы не блуждать по коду.


// Команды управления ботом через адресную строку браузера
// Установка и удаление web-хука
// https://bot.site.ru/bot.php?cmd=install
// https://bot.site.ru/bot.php?cmd=uninstall
if ( isset( $_GET["cmd"] ) ) {
    switch ( $_GET["cmd"] ) {

        case "uninstall":
            $answer = telegram( "setWebhook" );
            echo( var_export( $answer, true ) );
            return;
        break;

        case "install":
            $answer = telegram(
                "setWebhook",
                [
                    "url" => "https://{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}"
                ]
            );
            echo( var_export( $answer, true ) );
            return;
        break;

    }
}


// Список товаров
$products_php = __DIR__ . "/products.php";
if ( file_exists( $products_php ) && $fp = fopen( $products_php, "r" ) ) {
    // Блокируем файл чтобы не прочитать мусор в тот момент когда
    // работает команда записи в файл
    flock( $fp, LOCK_SH );
    include( $products_php );
    // разблокируем
    flock( $fp, LOCK_UN );
    fclose( $fp );
} else {
    $products = [];
}


// Считываем сохраненное состояние
// Все что нам нужно для работы бота, сохраняем в самом обычном php-файле
// в виде массива, который считывается командой include и все данные
// возвращаются на свои места.
$data_php = __DIR__ . "/data.php";
if ( file_exists( $data_php ) && $fp = fopen( $data_php, "r" ) ) {
    // Блокируем файл чтобы не прочитать мусор в тот момент когда
    // работает команда записи в файл
    flock( $fp, LOCK_SH );
    include( $data_php );
    // разблокируем
    flock( $fp, LOCK_UN );
    fclose( $fp );
} else {
    $data = [];
}


// А это функция для сброса состояния на диск
function save_data() {
    global $data;
    // Она тоже блокирует файл чтобы предотвратить одновременную запись
    $data_php = __DIR__ . "/data.php";
    $export = '<?php $data = ' . var_export( $data, true) . ";\n";
    $r = file_put_contents( $data_php, $export, LOCK_EX );
    return $r;
}


// Функция для ведения лога
function input_log( $var, $label = "" ) {
    if ( is_array( $var ) ) {
        $str = var_export( $var, true );
    } else {
        $str = $var;
    }
    $log = date( "Y-m-d H:i:s " ) . "{$label}{$str}\n";
    file_put_contents( __DIR__ . "/input.log", $log, FILE_APPEND );
}



// Работаем с входными данными



// Входные данные от Ноды
// Мы получаем id транзакции с которой затем и работаем.
// Сумма транзакции может быть и отрицательной, если это уведомление о том,
// что был перевод не в кошелек, а из него.
if ( isset( $argv ) ) {
    if ( count( $argv ) === 2 ) {
        input_log( $argv[1], '$argv[1] = ' );
        $txid = $argv[1];
        $tx = rpc( [ "method" => "gettransaction", "params" => [ $txid ] ] );
        input_log( $tx, '$tx = ' );

        // Обрабатываем транзакцию
        if ( $tx["error"] !== NULL ) {
            // Пришло уведомление об ошибке

        } elseif ( $tx["result"] !== NULL ) {

            $tx = $tx["result"];

            // Извлекаем заказ (адрес на который пришел перевод)
            $order_no = $tx["details"][0]["address"];

            // Фикс варнингов php при переброске входящего платежа
            // на свой внешний кошелек
            if ( isset( $data["orders"][ $order_no ] ) ) {
                $order = $data["orders"][ $order_no ];
                
                // Сверяем сумму
                if ( $order && $order["sum"] <= $tx["amount"] ) {
                    
                    // Проверяем заблокирована ли сумма в InstantSend
                    // Если нет, то не выдаем товар, а напишем что ждем подтверждений.
                    if ( $tx["instantlock"] === true || $tx["confirmations"] > 0 ) {
                    
                        // Отправляем товар пользователю
                        $sku = $order["sku"];
                        $r = telegram(
                            "sendMessage",
                            [
                                "chat_id" => $order["chat_id"],
                                "text" => $products[$sku]["product"],
                            ]
                        );

                        // Переправить дальше:
                        if ( ! empty( $bot["resend_to_address"] ) && $tx["amount"] > 0 ) {
                            // ./dash-cli help sendtoaddress
                            $resend = rpc( [
                                "method" => "sendtoaddress",
                                "params" => [
                                    $bot["resend_to_address"], $tx["amount"], "", "", true
                                ]
                            ] );
                            // После переправки, бот снова получит уведомление о выполнении операции. Там сумма будет отрицательной.
                            // Пишем в лог, прошла ли переброска. Если InstantSend не отработал, то не перебросит.
                            input_log( $resend, '$resend = ' );
                        }

                    } else {
                        // Сообщаем покупателю что ждем подтверждения
                        $r = telegram(
                            "sendMessage",
                            [
                                "chat_id" => $order["chat_id"],
                                "text" => "Мы видим ваш перевод, но InstantSend не был задействован. Ждем подтверждение сети.",
                            ]
                        );

                    }

                }

            }

        }
    } else {
        input_log( "Неверное количество аргументов:" );
        input_log( $argv, '$argv = ' );
    }

    return;
}


// Входные данные от Телеграма
$input_raw = file_get_contents( "php://input" );

if ( empty( $input_raw ) ) {
    return;
}

// Преобразуем входные данные в обычный массив
$input = json_decode( $input_raw, true ); // NULL если не парсится

if ( ! is_array( $input ) ) {
    return;
}

// Для отладки
input_log( $input, 'Telegram $input = ' );


// Диалог с пользователем

if ( ! empty( $input["message"] ) && $input["message"]["text"] === "/start" ) {

    // Создаем описание товаров и кнопки для покупки
    $user_name = $input["message"]["from"]["first_name"];
    $products_list = "Привет, {$user_name}! Выберите товар.\n\n";
    $buttons = [];
    foreach( $products as $sku => $prod ) {
        // Описание
        $products_list .= "{$prod["name"]}\n{$prod["description"]}\n\n";

        // Кнопка
        $but = [
            "text" => $prod["name"],
            "callback_data" => $sku, // артикул товара
        ];
        array_push( $buttons, $but );
    }
    // Логику разбивки товаров на строки кнопок можно еще доделать,
    // либо в ручную прописывать в какой строке должна быть кнопка,
    // либо чтобы автомат их разбрасывал сам.


    // Приветствуем и показываем кнопки.
    // https://core.telegram.org/bots/api#sendmessage
    // https://core.telegram.org/bots/api#inlinekeyboardmarkup
    $keyboard = [
        $buttons, // строка кнопок 1
        $buttons, // строка кнопок 2
    ];
    $markup = json_encode( [
        "inline_keyboard" => $keyboard,
    ] );
    $r = telegram(
        "sendMessage",
        [
            "chat_id" => $input["message"]["chat"]["id"],
            "text" => $products_list,
            "reply_markup" => $markup,
        ]
    );

} elseif ( ! empty( $input["callback_query"] ) ) { // Кто-то нажал на кнопку

    // Получаем у ноды адрес для оплаты
    $addr = rpc( [ "method" => "getnewaddress", "params" => [] ] );
    input_log( $addr, 'Dash $addr = ' );

    if ( empty( $addr ) ) {

        // Сообщаем об ошибке
        $r = telegram(
            "sendMessage", 
            [
                "chat_id" => $input["callback_query"]["message"]["chat"]["id"],
                "text" => "Dash-нода не выдала адрес.",
            ]
        );

        // Уведомляем Телеграм что получили запрос, чтобы крутяшка на кнопке остановилась
        // https://core.telegram.org/bots/api#answercallbackquery
        $r = telegram(
            "answerCallbackQuery",
            [
                "callback_query_id" => $input["callback_query"]["id"],
            ]
        );

        return;
    }

    $sku  = $input["callback_query"]["data"]; // артикул товара
    $curr = $products[$sku]["currency"];

    // Сумма
    // Записываем сумму в дешах.
    // Потому что курс деша, получаемый нами,
    // будет отличаться от курса, получаемого кошельком, на момент оплаты пользователем.
    // И с вероятность 50%+ оплата не пройдет.
    // 50% потому, что курс может либо расти, либо падать.
    // А + это разница курсов на разных сервисах прибавляет плохой вероятности.
    // Так же мы не будем ограничивать платеж по времени,
    // это отдельная сложная логика, которая может потребовать возвратов,
    // но кто-то может оплатить с биржи и возврат может быть потерян.
    if ( $curr === "dash" ) {

        $sum = $products[$sku]["price"];

    } elseif ( $curr === "rub" ) {

        update_dashrub(); // Обновляем курс
        $sum = round( $products[$sku]["price"] / $data["dashrub"]["price"], 6 );

    } else {

        // Неверная валюта
        $r = telegram(
            "sendMessage", 
            [
                "chat_id" => $input["callback_query"]["message"]["chat"]["id"],
                "text" => "Продавец был пьян и указал неверную валюту.",
            ]
        );

        // Уведомляем Телеграм что получили запрос, чтобы крутяшка на кнопке остановилась
        // https://core.telegram.org/bots/api#answercallbackquery
        $r = telegram(
            "answerCallbackQuery",
            [
                "callback_query_id" => $input["callback_query"]["id"],
            ]
        );
        return;

    }

    // Оформляем и сохраняем заказ.
    // Адрес на который будет поступать оплата считаем номером заказа
    // и сохраняем в $data["orders"]
    $addr = $addr["result"];
    $data["orders"][$addr] = [
        "user" => $input["callback_query"]["from"]["id"],
        "sku" => $sku,
        "chat_id" => $input["callback_query"]["message"]["chat"]["id"],
        "sum" => $sum,
    ];

    // Сохраняем данные
    save_data();

    // Наименование
    $name = $products[$sku]["name"];
    
    // Отправляем пользователю
    // Есть проблема со ссылками типа <a href='dash://{$addr}?amount={$sum}'>Оплатить</a>
    // https://github.com/tdlib/telegram-bot-api/issues/299
    // Телеграм их не пропускает.
    // Рекомендуют отправлять на сайт и там редиректить на адреса со схемой dash
    // Но такое решение нам пока не подходит.
    // И есть вторая проблема со ссылками
    // <a href='https://play.google.com/store/apps/details?id=hashengineering.darkcoin.wallet&launch=true&pay={$addr}&amount={$sum}'>Готовая ссылка для оплаты для Android</a>
    // Приложение запускается но не заполняет сумму и адрес. Видимо Гугл тоже срезает параметры.
    $r = telegram(
        "sendMessage",
        [
            "chat_id" => $input["callback_query"]["message"]["chat"]["id"],
            "parse_mode" => "HTML",
            "text" => "{$name}\n{$addr}\n{$sum} dash\nКопируйте все сообщение, мобильный кошелек сам найдет в нем адрес.",
            "disable_web_page_preview" => true,
        ]
    );

    // Уведомляем Телеграм что получили запрос, чтобы крутяшка на кнопке остановилась
    // https://core.telegram.org/bots/api#answercallbackquery
    $r = telegram(
        "answerCallbackQuery",
        [
            "callback_query_id" => $input["callback_query"]["id"],
        ]
    );
}


// Функция обновления курса
function update_dashrub() {
    global $data, $bot;
    if ( ! isset( $data["dashrub"]["date"] ) ) {
        $data["dashrub"]["date"] = 0;
    }
    if ( $data["dashrub"]["date"] < time() - 61 ) {
        $json = file_get_contents( "https://rates2.dashretail.org/rates?source=dashretail&symbol=dashrub" );
        $arr = json_decode( $json, true );
        if ( is_array( $arr ) ) {
            $data["dashrub"] = [
                "price" => $arr[0]["price"],
                "date" => time(),
            ];
            // Сохраняем данные
            save_data();
        }
    }
}