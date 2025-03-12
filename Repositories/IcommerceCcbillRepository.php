<?php

namespace Modules\Icommerceccbill\Repositories;

use Modules\Core\Repositories\BaseRepository;

interface IcommerceCcbillRepository extends BaseRepository
{

	public function calculate($parameters,$conf);
	
}
