## Описание

Это типовая клиентская библиотека, призванная обеспечить функциональность для работы с OAuth-сервером.
Базируется на библиотеке https://github.com/thephpleague/oauth2-client, распространяемой по лицензии MIT, которая сама по себе является готовым решением для создания OAuth-клиента. 

Примеры использования представлены в папке `example`.

1. [Настройка](#setup)
    1. [Скоупы и заголовок](#setup-detail)
2. [Запрос авторизации](#authorization-request)
3. [Получение токена](#authorization-response)
4. [Выход](#deauthorization)
5. [Авторизация в попап-окне](#popup-authorization)
5. [Авто-вход](#silent-authorization)
6. [Обработка ошибок](#error-handling)
7. [Авторизация по необходимости](#authorize-on-demand)
7. [Личный кабинет](#elk)
8. [Организация API сервера](#api-server)

<a name="setup"></a>
## Настройка 

Во-первых, разработчик должен написать свою реализацию работы с сессиями, унаследовав класс `\Codewiser\UAC\AbstractContext`. Дело в том, что разные приложения по разному работают с сессиями; кто-то хранит данные в cookies, кто-то в redis... Поди разбери )

Кто помнит описание протокола OAuth, тот знает, что запрос к серверу и ответ от него сопоставляются по параметру state, который приложение сохраняет в сессии. А вместе с параметром state приложение может хранить много другой полезной информации.

В самом простом случае потребуется что-то такое:

```php
class Context extends \Codewiser\UAC\AbstractContext
{
    protected function sessionSet($name, $value)
    {
        $_SESSION[$name] = $value;
    }

    protected function sessionGet($name)
    {
        return $_SESSION[$name];
    }

    protected function sessionHas($name)
    {
        return isset($_SESSION[$name]);
    }

    protected function sessionDel($name)
    {
        unset($_SESSION[$name]);
    }
}
```

Во-вторых, разработчик должен написать свой класс, который унаследует `\Codewiser\UAC\AbstractClient`. 
Это будет OAuth-клиент приложения.

Потребуется конкретизировать несколько абстрактных методов:

```php
use Codewiser\UAC\AbstractClient;

class UacClient extends AbstractClient
{
    protected function authorizeResourceOwner($user)
    {
        // Этот метод получает на вход объект `Codewiser\UAC\Model\User`.
        // Этот объект является представлением профиля пользователя, 
        //      который только что авторизовался на сервере.
        // Документация: https://oauth.fc-zenit.ru/doc/api/objects/user/
    
        // Мы должны найти соответствующего локального пользователя,
        //      и авторизовать его.
    
        // Например, будем искать пользователя по email, 
        //      который пользователь указал в качестве логина.
        // Но если пользователь в качестве логина использовал номер телефона,
        //      то возьмем первый email пользователя.
        $email = filter_var($user->login, FILTER_VALIDATE_EMAIL) ?: $user->email[0];

        // Теперь найдем пользователя с этим email в нашей базе данных.
        $localUser = User::find(['email' => $email])->first(); 
    
        // Если такого пользователя нет, то создадим его.
        if (!$localUser) {
            $localUser = new User();
            $localUser->email = $email;
            $localUser->name = $user->name;
            $localUser->save();
        }
    
        // И в конце концов авторизуем нашего пользователя
        $_SESSION['authorized_user_id'] = $localUser->id;
    }
    
    protected function deauthorizeResourceOwner()
    {
        // В этом методе мы должны разавторизовать нашего пользователя:
        $_SESSION['authorized_user_id'] = null;
    }

    public function defaultScopes()
    {
        // Здесь мы объявляем скоупы, 
        //      с которыми по умолчанию происходит авторизация пользователей.
        // Документация: https://oauth.fc-zenit.ru/doc/oauth/scope/
        return ['phone', 'mobile'];
    }
    
    public function log($message, array $context = [])
    {
        // Если хотите, можете писать логи авторизации.
        //      Или не писать...
    }
}
```

Для удобства рекомендуется написать статический метод инстанциирования экземпляра класса.

```php
use Codewiser\UAC\AbstractClient;
use Codewiser\UAC\Connector;

class UacClient extends AbstractClient
{
    public static function instance()
    {
        // С помощью коннектора мы передаем параметры подключения к серверу.
        $connector = new Connector(
            getenv('OAUTH_SERVER_URL'),
            getenv('CLIENT_ID'),
            getenv('CLIENT_SECRET'),
            getenv('REDIRECT_URI'),
            new Context()
        );
        return new static($connector);
    }
}
```

Теперь мы можем обратиться к нашему oauth-клиенту из любого места приложения легко и просто. Например:
```php
UacClient::instance()->getAccessToken();
```

**Все написанное выше является художественной выдумкой, а все совпадения случайны. Разработчик не должен буквально следовать приведенным примерам.**

<a name="setup-detail"></a>
### Скоупы и заголовок

При объявлении класса `UacClient` мы реализовали метод `defaultScopes`, в котором объявили `scopes`, которые нужны нашему сайту.
Кроме это мы можем настроить заголовок, который будет отображаться пользователю во время авторизации.

```php
use Codewiser\UAC\AbstractClient;

class UacClient extends AbstractClient
{
    public function defaultScopes()
    {
        return ['phone', 'mobile'];
    }
    public function defaultAuthorizationHint()
    {
        return 'Заголовок авторизации';
    }
}
```

<a name="authorization-request"></a>
## Запуск авторизации

Чтобы начать авторизацию, мы должны сформировать специальную ссылку на сервер и отправить пользователя по ней.

Получить эту ссылку можно методом `UacClient::instance()->getAuthorizationUrl()`.

Эта ссылка длинная и некрасивая, поэтому мы рекомендуем сделать страницу авторизации с лаконичным адресом, а в её контроллере делать редирект на сервер авторизации.

```html
<a href="login.php">Authorize</a>
```

_login.php_
```php
<?php

$uac = UacClient::instance();

// После авторизации вернем пользователя туда, откуда он пришел.
$uac->setReturnPath($_SERVER['HTTP_REFERER']);

// Можно установить заголовок, который пользователь увидит на OAuth-сервере.
$uac->setAuthorizationHint('Мой собственный заголовок окна авторизации');

// Этой инструкцией можно переопределить список scope по умолчанию.
$uac->setScope('read write jump fly');

// Отправляем пользователя на сервер за авторизацией
header('Location: ' . $uac->getAuthorizationUrl());

exit;
```

Отлично, пользователь отправлен на сервер, где пройдет унизительную процедуру авторизации. Скоро он вернется назад, надо подготовить его встречу.

<a name="authorization-response"></a>
## Завершение авторизации

Для корректной работы oauth-протокола разработчик должен сделать специальную страницу — которая будет обслуживать редирект с сервера. Это то, что мы обычно называем callback.

Её логика проста. Принять запрос, передать его в класс oauth-клиента, обработать ошибки, вернуть пользователя на страницу, откуда он начал авторизацию.

_callback.php_
```php
<?php

$uac = UacClient::instance();

try {
    
    // Для обработки поступившего запроса написан специальный метод.
    //      Передайте в него реквест, всё остальное он сделает сам.
    $uac->callbackController($_GET);

    // Если пользователь откуда-то пришел, то пусть идет обратно
    // Если мы были в popup, то закроем его
    if (!$uac->closePopup()) {
        header('Location: ' . $uac->getReturnPath('/'));
        exit();
    }

} catch (Exception $e) {

    var_dump($e);
    die();
}
```

Обратите внимание на метод `$uac->getReturnPath()`. Он возвращает адрес, который мы ранее установили с помощью метода `$uac->setReturnPath()`. То есть после завершения авторизации мы вернем пользователя туда, откуда он начал. А если сохраненного адреса нет, то пользователь вернется на страницу `/`. Ведь пользователя всё равно нужно куда-то вернуть.

<a name="deauthorization"></a>
## Выход

Для полного выхода из системы, то есть для давторизации и на сайте, и на OAuth-сервере,
вы должны отправить пользователя по адресу `UacClient::instance()->getDeauthorizationUrl()`.

_logout.php_
```php
<?php

$uac = UacClient::instance();

// После выхода вернем пользователя на главную страницу.
$uac->setReturnPath('/');

header('Location: ' . $uac->getDeauthorizationUrl());

exit;
```

<a name="popup-authorization"></a>
## Авторизация в Popup

В колбеке вы видели вызов `$uac->closePopup()`. Если процесс авторизации происходил во всплывающем окне, то этот метод закроет всплывающее окно.

А теперь мы расскажем, как запустить процесс авторизации во всплывающем окне.

Во-первых, к странице нужно подключить java-script, который можно найти по адресу `example/public/assets/js/oauth.js`. Этот скрипт занимается открытием всплывающего окна и отслеживает его закрытие.

Перехватываем нажатие пользователя по ссылке авторизации, чтобы открыть всплывающее окно:

```html
<a href="login-in-popup.php"
    onclick="oauth(this.href, function() {/* Делаем что-то, когда поп-ап закрылся */}); return false;">Authorize in Popup</a>
```

Далее, перед редиректом пользователя на сервер, нужно запомнить в сессии, что авторизации происходит в popup.

_login-in-popup.php_
```php
<?php
$uac = UacClient::instance();

// Поставим флаг, что открыто в поп-апе
$uac->setRunInPopup(true);

// Отправляем пользователя на сервер за авторизацией
header('Location: ' . $uac->getAuthorizationUrl());

exit;
```

Теперь стала понятна запись в колбеке:
```php
if (!$uac->closePopup()) {
    header('Location: ' . $uac->getReturnPath('/'));
    exit();
}
``` 
Это означает — если авторизация запущена в popup, то закроем всплывающее окно, а если нет, то сделаем редирект туда, откуда начали; в крайнем случае — на главную страницу.

<a name="silent-authorization"><a/>

## Авто-вход

Это такой сценарий авторизации, во время которого пользователь не взаимодействует с сервером авторизации — не видит форму, не вводит логин и пароль.
Если сервер «помнит» пользователя, то процесс авторизации пройдет успешно и сайт получит токен.
Если сервер не «помнит» пользователя, то процесс авторизации завершится с ошибкой.

```php
<?php
$uac = UacClient::instance();

$uac->setPrompt('none');

// Отправляем пользователя на сервер за авторизацией
header('Location: ' . $uac->getAuthorizationUrl());

exit;
```

<a name="error-handling"></a>
## Обработка ошибок

Остановимся подробнее на обработке ошибок, возникающих во время авторизации. Полный их список можно найти здесь https://oauth.fc-zenit.ru/doc/oauth/authorization/the-authorization-response/

Все ошибки можно разделить на три типа. Первые — которые возникают, если разработчик что-то неправильно сделал. Например, напутал с `client_id`. Такие ошибки не выпадают пользователю после успешного релиза продукта.

Другие ошибки — это физические ошибки на сервере. Например, `server_error` или `temporarily_unavailable`. Такие ошибки рекомендуется обработать и предложить пользователю попробовать позднее еще раз.

И одна ошибка — `access_denied` — вызвана непосредственными действиями пользователя — он прервал процесс авторизации. То есть её нельзя считать ошибкой в строгом смысле этого слова. Поэтому такую ошибку мы рекомендуем обрабатывать так же, как и успешную авторизацию — то есть возвращать пользователя туда, откуда он начал. Пользователь захотел авторизоваться, потом передумал, сайт вернул его обратно — красивый сценарий. Лучше, чем выбрасывать ошибку.

_callback.php_
```php
<?php
try {

} catch (\Codewiser\UAC\Exception\OauthResponseException $e) {

    if ($e->getMessage() == 'access_denied') {
        // Авторизацию прервал сам пользователь
        // Поэтому не считаем это ошибкой
        if (!$uac->closePopup()) {
            header('Location: ' . $uac->getReturnPath('/'));
            exit();
        }
    }

    var_dump($e);
    die();
} catch (Exception $e) {

    var_dump($e);
    die();
}
```

<a name="authorize-on-demand"></a>
## Авторизация по требованию / защищенные страницы

На каждом сайте есть страницы, доступ к которым есть только у авторизованного пользователя. Это и админка, это и личный кабинет.

В самом простом случае сайт проверяет наличие авторизации пользователя, и если её нет, то вместо страницы показывает ошибку.

В более продвинутых реализациях сайт не показывает ошибку, а предлагает пользователю авторизоваться. Так же предлагаем и мы.

Сценарий нашего рецепта такой:

1. Проверим авторизацию пользователя.
1. Если авторизации нет, то отправим пользователя на сервер.
1. Если пользователь успешно авторизовался, то вернем его на исходную страницу.
1. Если произошла ошибка, то запомним её, и вернем пользователя на исходную страницу.
    1. На исходной странице проверим наличие ошибки.

Во-первых, модифицируем обработку ошибок в колбеке — будем запоминать в сессии, что пользователь сам прервал процесс авторизации:

_callback.php_
```php
<?php

$uac = UacClient::instance();

try {
    //
} catch (\Codewiser\UAC\Exception\OauthResponseException $e) {

    if ($e->getMessage() == 'access_denied') {
        if (!$uac->closePopup()) {

            // Сохраним ошибку в сессии!
            $_SESSION['oauth-exception'] = serialize($e);

            header('Location: ' . $uac->getReturnPath('/'));
            exit();
        }
    }

    var_dump($e);
    die();
}
```

Во-вторых, напишем метод для нашего oauth-клиента, который будет проверять наличие авторизации пользователя.
При этом, если мы обнаружим, что в сессии есть ошибка от предыдущей попытки, то покажем пользователю 403 ошибку:

```php
use Codewiser\UAC\AbstractClient;

class UacClient extends AbstractClient
{
    /**
     * Если авторизации нет, то отправим пользователя на oauth-сервер
     * @param string $returnPath после авторизации вернем пользователя на этот адрес
     */
    public function requireAuthorization($returnPath)
    {
        if (!$this->hasAccessToken() || !$_SESSION['authorized_user_id']) {
            if (isset($_SESSION['oauth-exception'])) {
                unset($_SESSION['oauth-exception']);
                header('HTTP/1.0 403 Forbidden');
                echo 'Authorization Required!';
                die();
            }
            $this->setReturnPath($returnPath);
            header('Location: ' . $this->getAuthorizationUrl());
            exit;
        }
    }
}
```

Теперь мы можем защитить любую страницу:

```php
<?php

UacClient::instance()->requireAuthorization($_SERVER['REQUEST_URI']);

// А здесь идёт защищенное содержание
?>
```

И получается, что если пользователь хочет попасть на защищенную страницу, мы отправляем его авторизовываться. 
А если он передумал авторизовываться, то мы показываем ему 403 ошибку. 
Но стоит пользователю обновить страницу, как сценарий начинается заново — всё благодаря тому, что мы каждый раз очищаем сессию от сохраненной ошибки.

<a name="elk"></a>
## Личный кабинет

Получив токен доступа мы можем встроить на свой сайт личный кабинет пользователя.

Напомним, что личный кабинет должен иметь адрес `/elk`, а для его работоспособности необходим полдключенный `jQuery`.

_/elk/index.php_

```php
$uac = UacClient::instance();

// убедимся, что пользователь авторизован и у нас есть его токен
$uac->requireAuthorization($_SERVER['REQUEST_URI']);

// получим данные для построения личного кабинета, 
// передадим ссылку деавторизации пользователя,
// передадим адрес api сервиса билетов
$office = $uac
    ->setLocale('en')
    ->getOnlineOffice(
        'http://example.com/logout',
        'http://exapmle.com/api/tickets'
    );

// если у вас нет своего jquery
echo $office->assetJQuery();

echo $office->assetHtml();
echo $office->assetStyles();
echo $office->assetScripts();
```


<a name="api-server"></a>
## Организация API сервера

Эта библиотека содержит инструменты для создания API-серверов, 
которые позволяют авторизовать поступающие API-запросы 
на OAuth-сервере организации.

Авторизация поступающих запросов производится в соответствии 
с RFC 6750.

Напомним сценарий. 

1. Пользователь заходит на сервер А и авторизует его на OAuth-сервере. Теперь у сервера А есть токен доступа пользователя.
1. Сервер А хочет использовать API сервера В. Для этого он подписывает свой запрос к серверу В токеном доступа пользователя.
1. Сервер В, получив такой запрос, обращается к OAuth-серверу и проверяет поступивший токен доступа.
    1. Если OAuth-сервер подтверждает, что он действительно выдал этот токен доступа, то сервер В выполняет запрос сервера А.
    1. Если OAuth-сервер сообщает, что токен не существует, то сервер В возвращает серверу А ошибку.


```php
$uac = UacClient::instance();

try {
    // Если запрос не авторизован, то будет выброшено исключение
    // Если запрос прошел проверку, то вернется информация о токене
    $tokenInfo = $uac->apiRequestAuthorize(getallheaders(), $_REQUEST);
    
    // В контроллере разработчик должен убедиться, что токен дает доступ к функциональности 
    if (!$tokenInfo->hasScope('read')) {
        throw new \Codewiser\UAC\Exception\Api\InsufficientScopeException('test');
    }

    // Разработчик проверяет наличие обязательных полей
    if (!isset($_REQUEST['test'])) {
        throw new \Codewiser\UAC\Exception\Api\InvalidRequestException("Missing 'test' parameter");
    }

    echo json_encode('ok');

} catch (\Codewiser\UAC\Exception\Api\RequestException $e) {
    $uac->apiRespondWithError($e);
}

exit;
``` 

Разработчик может переопределить методы `apiRequestAuthorize` и `apiRespondWithError`, чтобы адаптировать их поведение к своим особенностям выполнения программы.
