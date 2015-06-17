<?php namespace Fhferreira\SintegraCnpjSpGratis;

class Facade extends \Illuminate\Support\Facades\Facade {

    protected static function getFacadeAccessor() {
        return 'sintegra_cnpj_sp_gratis';
    }

}