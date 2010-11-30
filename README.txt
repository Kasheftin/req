
Req 0.1

Класс для HTTP-запросов через обычный socket.

Идея:
	класс нужен для выполнения серий запросов, когда нужно сохранять между ними cookies (авторизовываться) и выполнять автоматические редиректы.

Базовый пример:

$req = new Req();
$req	->set(array("method"=>"POST","host"=>"obzor.lt","url"=>"/profile/signin/"))
	->req(array("username"=>$username,"password"=>$password))
	->set(array("method"=>"GET","url"=>"/profile/"))
	->req()
	->get("cookies",$cookies)
	->save($content);

- Отправляет POST-запрос на /profile/signin/, сохраняет куки и с ними делает GET-запрос на страницу /profile/.
Потом сохраняет куки в $cookies и ответ от последнего запроса в $content.

