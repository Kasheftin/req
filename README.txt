$req = new Req();
$req->set(array("method"=>"GET","host"=>"obzor.lt","url"=>"/"))->do()
	->saveContent($content)
	->save($all)
	->saveHeader($header)
	->set("method","POST")->set("url","/profile/signin/")->do(array("email"=>$formData[email],"password"=>$formData["password"]))
	->saveCookies($cookies)
	->b()
		->set("method","GET")
		->set("Accept-Language","en")
		->set("autoredirects",true)
		->set("autoupdatecookies",true)
		->do()
		->save($all)
	->e()            	
	->set("cookies",$_COOKIE)
	->add("cookies",array("SID",$SID))
	->add("cookies","SID",$SID)
	->add(array("cookies"=>array("SID"=>$SID)))
	->add(array("cookies"=>"SID",$SID))

->set("cookies","SID",$SID) - добавляет (или переписывает) куку SID, другие cookies не трогает
->set("cookies",array("SID",$SID)) - переписывает все куки
->add("cookies",array("SID",$SID)) - добавляет в куки

$req->do("")


add метода нет, есть только set метод, который действует как add. + есть clear-метод если нужно что-то обнулить.