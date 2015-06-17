<?php namespace Fhferreira\SintegraCnpjSpGratis;

use Illuminate\Support\ServiceProvider;

class SintegraCnpjSpGratisServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot() {
        $this->package('fhferreira/sintegra-cnpj-sp-gratis');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        $this->app->singleton('sintegra_cnpj_sp_gratis', function() {
            return new \Fhferreira\SintegraCnpjSpGratis\SintegraCnpjSpGratis;
        });
    }

}
