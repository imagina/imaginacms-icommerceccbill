<?php

namespace Modules\Icommerceccbill\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Traits\CanPublishConfiguration;
use Modules\Core\Events\BuildingSidebar;
use Modules\Core\Events\LoadingBackendTranslations;
use Modules\Icommerceccbill\Events\Handlers\RegisterIcommerceccbillSidebar;

class IcommerceccbillServiceProvider extends ServiceProvider
{
    use CanPublishConfiguration;
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerBindings();
        $this->app['events']->listen(BuildingSidebar::class, RegisterIcommerceccbillSidebar::class);

        $this->app['events']->listen(LoadingBackendTranslations::class, function (LoadingBackendTranslations $event) {
            $event->load('icommerceccbills', Arr::dot(trans('icommerceccbill::icommerceccbills')));
            // append translations

        });
    }

    public function boot()
    {
        $this->publishConfig('icommerceccbill', 'permissions');
        $this->publishConfig('icommerceccbill', 'config');
        $this->publishConfig('icommerceccbill', 'crud-fields');

        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }

    private function registerBindings()
    {
        $this->app->bind(
            'Modules\Icommerceccbill\Repositories\IcommerceCcbillRepository',
            function () {
                $repository = new \Modules\Icommerceccbill\Repositories\Eloquent\EloquentIcommerceCcbillRepository(new \Modules\Icommerceccbill\Entities\IcommerceCcbill());

                if (! config('app.cache')) {
                    return $repository;
                }

                return new \Modules\Icommerceccbill\Repositories\Cache\CacheIcommerceCcbillDecorator($repository);
            }
        );
// add bindings

    }

    
}
