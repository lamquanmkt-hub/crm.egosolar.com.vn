<?php

namespace App\Repositories\Interfaces;

interface LeadRepositoryInterface
{
	public function count();
	public function getRecent($limit = 5);
}
