##Extração de dados Sintegra

###Package com Crawler para extração gratuita de dados utilizando CNPJ's diretamente no site do sintegra de sp.

####Módulo baseado no formato utilizado por JansenFelipe para extração no site da Receita, por meio do CPF e CNPJ.

```php
<?php
//Captura dos dados e cookie
$params = \Fhferreira\SintegraCnpjSpGratis\SintegraCnpjSpGratis::getParams();

//Envio dos Dados via POST e captura da Resposta
$returnCrawler = \Fhferreira\SintegraCnpjSpGratis\SintegraCnpjSpGratis::consulta(
                                                                                    $_POST['cnpj'],
                                                                                    null,
                                                                                    $_POST['paramBot'],
                                                                                    $_POST['captcha'],
                                                                                    $_POST['cookie']
                                                                                );
```

Exemplo utilizando Silex + Twig
```php
<?php
    $app->get('/cnpj-sintegra-sp', function() use($app) {
        $cnpj   = $app['session']->getFlashBag()->get('cnpj');
        $cnpj   = count($cnpj)?$cnpj[0]:'';
        $ie     = $app['session']->getFlashBag()->get('ie');
        $ie     = count($ie)?$ie[0]:'';
        $params = \Fhferreira\SintegraCnpjSpGratis\SintegraCnpjSpGratis::getParams();
        return $app['twig']->render('cnpj/form-sintegra.twig', compact('cnpj', 'ie', 'params'));
    });

    $app->post('/cnpj-sintegra-sp', function() use($app, $environment) {
        try {
            $return = \Fhferreira\SintegraCnpjSpGratis\SintegraCnpjSpGratis::consulta($_POST['cnpj'],$_POST['ie'],$_POST['paramBot'],$_POST['captcha'], $_POST['cookie']);
        } catch(\Exception $e) {
            $app['session']->getFlashBag()->add('message',  $e->getMessage() );//. ' \nLine:' . $e->getLine(). ' \nFile:' . $e->getFile() . "\n" . $e->getTraceAsString()
            $app['session']->getFlashBag()->add('cnpj', $_POST['cnpj']);
            $app['session']->getFlashBag()->add('ie', $_POST['ie']);
            return $app->redirect('/cnpj-sintegra-sp');
        }
        header('Content-Type: application/json');
        //foreach($return as $k => $v) {
        //    $return[$k] = encodeToUtf8($v);
        //}
        echo json_encode(($return));
        die();
    });
?>
```

##View
```php
{# views/cnpj/form-sintegra.twig #}
{% extends 'layout.twig' %}

{% block title %}Consulta CNPJ Sintegra{% endblock %}

{% block body %}
    <form action="" method="POST">
        <img src="{{ params['captchaBase64'] }}" class="img-thumbnail" />
        <br/><br/>

        {% for message in app.session.getFlashBag.get('message') %}
            <span style="color:red;font-size:20px;">{{ message }}</span>
            <br/>
        {% endfor %}

        <input type="hidden" name="cookie" value="{{ params['cookie'] }}" />
        <input type="hidden" name="paramBot" value="{{ params['paramBot'] }}" />

        <div class="form-group">
            <input type="text" name="captcha" placeholder="Captcha" required="required" class="form-control"/>
            <p class="help-block">Digite corretamente o captcha.</p>
        </div>
        <div class="form-group">
            <input type="text" name="cnpj" id="cnpj" placeholder="Cnpj" required="required"  class="form-control" value="{{ cnpj?:'' }}" />
            <p class="help-block">Digite corretamente o CNPJ.</p>
        </div>
        <input type="submit" value="Consultar" class="btn btn-success" />
    </form>
{% endblock %}
```

##Blade + Lumen/Laravel

```php
$app->get('/cnpj-sintegra-sp', "App\Http\Controllers\CnpjSintegraController@getForm");
$app->post('/cnpj-sintegra-sp', "App\Http\Controllers\CnpjSintegraController@postForm");
```

```php
<?php namespace App\Http\Controllers;

use DB;
use Session;

class CnpjSintegraController extends Controller
{
	public function getForm()
	{
		$cnpj = session('cnpj');
		$ie   = session('ie');
		$params = \Fhferreira\SintegraSpGratis\SintegraSpGratis::getParams();
    	return view('cnpj.form-sintegra', compact('cnpj', 'ie', 'params'));
	}

	public function postForm()
	{
	    try {
	        $returnCrawler = \Fhferreira\SintegraSpGratis\SintegraSpGratis::consulta($_POST['cnpj'],$_POST['ie'],$_POST['paramBot'],$_POST['captcha'], $_POST['cookie']);
	    } catch(\Exception $e) {
	        Session::flash('message', $e->getMessage() );
	        Session::flash('cnpj', $_POST['cnpj']);
	        return redirect('/cnpj-sintegra-sp');
	    }	    
	    return $returnCrawler;
	}
}
```


```php
@extends("layout")

@section("title")
Consulta CNPJ Sintegra
@stop

@section("body")
    <form action="" method="POST">
        <img src="{{ $params['captchaBase64'] }}" class="img-thumbnail" />
        <br/><br/>

        @if(Session::has("message"))
            <span style="color:red;font-size:20px;">{{ Session::get("message") }}</span>
            <br/>
        @endif

        <input type="hidden" name="cookie" value="{{ $params['cookie'] }}" />
        <input type="hidden" name="paramBot" value="{{ $params['paramBot'] }}" />

        <div class="form-group">
            <input type="text" name="captcha" placeholder="Captcha" required="required" class="form-control"/>
            <p class="help-block">Digite corretamente o captcha.</p>
        </div>
        <div class="form-group">
            <input type="text" name="cnpj" id="cnpj" placeholder="Cnpj" required="required"  class="form-control" value="{{ $cnpj?:'' }}" />
            <p class="help-block">Digite corretamente o CNPJ.</p>
        </div>
        <!--
        <div class="form-group">
            <input type="text" name="ie" placeholder="Inscrição Estadual" class="form-control" value="{{ $ie }}" />
            <p class="help-block">Digite corretamente o IE.</p>
        </div>
        //-->
        <input type="hidden" name="ie"/>
        <input type="submit" value="Consultar" class="btn btn-success" />
    </form>
@stop

@section("onload")
    //$('#cnpj').mask("99.999.999/9999-99");
@stop
```