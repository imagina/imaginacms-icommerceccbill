<?php

namespace Modules\Icommerceccbill\Repositories\Cache;

use Modules\Icommerceccbill\Repositories\IcommerceCcbillRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheIcommerceCcbillDecorator extends BaseCacheDecorator implements IcommerceCcbillRepository
{
    public function __construct(IcommerceCcbillRepository $icommerceccbill)
    {
        parent::__construct();
        $this->entityName = 'icommerceccbill.icommerceccbills';
        $this->repository = $icommerceccbill;
    }

    public function calculate($parameters,$conf)
    {
        
        return $this->repository->calculate($parameters, $conf);
        
    }
    
}
