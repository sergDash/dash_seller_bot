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
    ./dashd -instantsendnotify="php /путь/к/нашему/боту/bot.php %s"
    Нода будет непосредственно уведомлять нашего бота о поступающих транзакциях.
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
function telegram( $cmd, $data = array() ) {
    global $bot;
    $curl = curl_init();
    curl_setopt_array( $curl, array(
        CURLOPT_URL => "https://api.telegram.org/bot{$bot['token']}/{$cmd}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_POSTFIELDS => http_build_query( $data ),
    ) );
    $resp = curl_exec( $curl );
    curl_close( $curl );
    // Для отладки раскомментируйте
    //file_put_contents( __DIR__ . "/input.log", $resp . "\n", FILE_APPEND );
    return json_decode( $resp, true );
}


// Функция для работы с Dash RPC
function rpc( $data = array() ) {
    global $bot;
    $data["jsonrpc"] = "1.0";
    $data["id"] = microtime();
    $curl = curl_init();
    $json = json_encode( $data );
    curl_setopt_array( $curl, array(
        CURLOPT_URL => $bot["dashd_url"],
        CURLOPT_PORT => $bot["dashd_port"],
        CURLOPT_USERPWD => "{$bot['rpcuser']}:{$bot['rpcpassword']}",
        CURLOPT_HEADER => 0,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 1,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => array( 'Content-type: text/plain', 'Content-length: ' . strlen( $json ) ),
    ) );
    $resp = curl_exec( $curl );
    curl_close( $curl );
    //file_put_contents( __DIR__ . "/input.log", '$resp = ' . var_export( $resp, true) . ";\n", FILE_APPEND );
    return json_decode( $resp, true );
}


// Команды управления ботом через адресную строку браузера
// Установка и удаление web-хука
// https://bot.site.ru/bot.php?cmd=install
// https://bot.site.ru/bot.php?cmd=uninstall
if ( isset( $_GET["cmd"] ) ) {
    switch ( $_GET["cmd"] ) {

        case "uninstall":
            exit( var_export( telegram( "setWebhook" ), true ) );
        break;

        case "install":
            exit( var_export( telegram( "setWebhook", array( "url" => "https://{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}" ) ), true ) );
        break;

    }
}


// Список товаров
if ( file_exists( __DIR__ . "/products.php" ) && $products_file = fopen( __DIR__ . "/products.php", "r" ) ) {
    // Блокируем файл чтобы не прочитать мусор в тот момент когда
    // работает команда записи в файл
    flock( $products_file, LOCK_SH );
    include( __DIR__ . "/products.php" );
    // разблокируем
    flock( $products_file, LOCK_UN );
} else {
    $products = array();
}


// Считываем сохраненное состояние
// Все что нам нужно для работы бота, сохраняем в самом обычном php-файле
// в виде массива, который считывается командой include и все данные
// возвращаются на свои места.
if ( file_exists( __DIR__ . "/data.php" ) && $data_file = fopen( __DIR__ . "/data.php", "r" ) ) {
    // Блокируем файл чтобы не прочитать мусор в тот момент когда
    // работает команда записи в файл
    flock( $data_file, LOCK_SH );
    include( __DIR__ . "/data.php" );
    // разблокируем
    flock( $data_file, LOCK_UN );
} else {
    $data = array();
}


// А это функция для сброса состояния на диск
function save_data() {
    global $data;
    // Она тоже блокирует файл чтобы предотвратить одновременную запись
    @$r = file_put_contents( __DIR__ . "/data.php", '<?php $data = ' . var_export( $data, true) . ";\n", LOCK_EX );
    return $r;
}



// Работаем с входными данными



// Входные данные от Ноды
// Мы получаем id транзакции с которой затем и работаем.
// Сумма транзакции может быть и отрицательной, если это уведомление о том,
// что был перевод не в кошелек, а из него.
if ( isset( $argv ) && count( $argv ) > 1 ) {
    $txid = $argv[1];
    //file_put_contents( __DIR__ . "/input.log", "----- " . date( "Y-m-d H:i:s", time() ) . " -----\n", FILE_APPEND );
    //file_put_contents( __DIR__ . "/input.log", '$argv = ' . var_export( $argv, true) . ";\n", FILE_APPEND );
    $tx = rpc( array( "method" => "gettransaction", "params" => [ $txid ] ) );
    //file_put_contents( __DIR__ . "/input.log", '$tx = ' . var_export( $tx, true) . ";\n", FILE_APPEND );

    // Обрабатываем транзакцию
    if ( $tx["error"] !== NULL ) {
        // Пришло уведомление об ошибке

    } elseif ( $tx["result"] !== NULL ) {

        // Извлекаем заказ (адрес на который пришел перевод)
        $order = $data["orders"][ $tx["result"]["details"][0]["address"] ];
        
        // Сверяем сумму
        if ( $order && $products[ $order["sku"] ]["price"] <= $tx["result"]["amount"] ) {
            // Отправляем товар пользователю
            $r = telegram( "sendMessage", array(
                "chat_id" => $order["chat_id"],
                "text" => $products[ $order["sku"] ]["product"],
            ) );
        }


        // Переправить дальше:
        if ( ! empty( $bot["resend_to_address"] ) && $tx["result"]["amount"] > 0 ) {
            // ./dash-cli sendtoaddress "address" amount ( "comment" "comment_to" subtractfeefromamount use_is use_cj conf_target "estimate_mode" avoid_reuse )
            $resend = rpc( array( "method" => "sendtoaddress", "params" => [ $bot["resend_to_address"], $tx["result"]["amount"], "", "", true, true ] ) );
            // После переправки, бот снова получит уведомление о выполнении операции. Там сумма будет отрицательной.
            //file_put_contents( __DIR__ . "/input.log", '$resend = ' . var_export( $resend, true) . ";\n", FILE_APPEND );
        }
    }


}


// Входные данные от Телеграма
$input_raw = file_get_contents( "php://input" );

if ( empty( $input_raw ) ) {
    exit();
}

// Преобразуем входные данные в обычный массив
$input = json_decode( $input_raw, true );
// NULL если не парсится

if ( ! is_array( $input ) ) {
    exit();
}

// Для отладки
file_put_contents( __DIR__ . "/input.log", "----- " . date( "Y-m-d H:i:s", time() ) . " -----\n", FILE_APPEND );
file_put_contents( __DIR__ . "/input.log", var_export( $input, true ) . "\n", FILE_APPEND );


// Диалог с пользователем

if ( ! empty( $input["message"] ) && $input["message"]["text"] === "/start" ) {
    // Регистрируем пользователя
    $data["users"][ $input["message"]["from"]["id"] ] = array(
        "date" => date( "Y-m-d H:i:s", $input["message"]["date"] ),
        "chat_id" => $input["message"]["chat"]["id"],
    );
    
    // Сохраняем данные
    save_data();

    // Создаем описание товаров и кнопки для покупки
    $products_list = "Привет, {$input["message"]["from"]["first_name"]}! Выберите товар.\n\n";
    $buttons = array();
    foreach( $products as $sku => $prod ) {
        // Описание
        $products_list .= "{$prod["name"]}\n{$prod["description"]}\n\n";

        // Кнопка
        $but = array(
            "text" => $prod["name"],
            "callback_data" => $sku,
        );
        array_push( $buttons, $but );
    }


    // Приветствуем и показываем кнопки.
    // https://core.telegram.org/bots/api#sendmessage
    // https://core.telegram.org/bots/api#inlinekeyboardmarkup
    $r = telegram( "sendMessage", array(
        "chat_id" => $input["message"]["chat"]["id"],
        "text" => $products_list,
        "reply_markup" => json_encode( array( "inline_keyboard" => array(
            $buttons
        ) ) ),
    ) );

} elseif ( ! empty( $input["callback_query"] ) ) { // Кто-то нажал на кнопку

    // Получаем у ноды адрес для оплаты
    $addr = rpc( array( "method" => "getnewaddress", "params" => [] ) );
    file_put_contents( __DIR__ . "/input.log", '$addr = ' . var_export( $addr, true) . ";\n", FILE_APPEND );
    
    // Записываем адрес и sku товара
    // Адрес на который будет поступать оплата считаем номером заказа
    // и сохраняем в $data["orders"]
    $addr = $addr["result"];
    $data["orders"][$addr] = array(
        "user" => $input["callback_query"]["from"]["id"],
        "sku" => $input["callback_query"]["data"],
        "chat_id" => $input["callback_query"]["message"]["chat"]["id"]
    );

    // Сохраняем данные
    save_data();

    // Наименование
    $name = $products[ $input["callback_query"]["data"] ]["name"];

    // Отправляем пользователю
    $r = telegram( "sendMessage", array(
        "chat_id" => $input["callback_query"]["message"]["chat"]["id"],
        "text" => "{$addr}\n{$name}",
    ) );

    // Уведомляем Телеграм что получили запрос, чтобы крутяшка на кнопке остановилась
    // https://core.telegram.org/bots/api#answercallbackquery
    $r = telegram( "answerCallbackQuery", array(
        "callback_query_id" => $input["callback_query"]["id"],
    ) );
}