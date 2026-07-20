<?php

namespace App\Contracts\Repositories;

/**
 * Interface khai báo các thao tác repository cho lead.
 */
interface LeadRepositoryInterface
{
    /**
     * Đếm tổng số lead.
     *
     * @return mixed
     */
    public function count();

    /**
     * Lấy danh sách lead mới nhất.
     *
     * @param  int  $limit
     * @return mixed
     */
    public function getRecent($limit = 5);
}
