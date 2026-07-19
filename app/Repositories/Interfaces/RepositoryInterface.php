<?php

namespace App\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Model;

/**
 * Base repository interface định nghĩa các method chung
 * Tuân thủ Dependency Inversion Principle
 */
interface RepositoryInterface
{
    /**
     * Lấy tất cả records
     */
    public function all();

    /**
     * Tìm record theo ID
     */
    public function find($id): ?Model;

    /**
     * Tạo record mới
     */
    public function create(array $data): Model;

    /**
     * Cập nhật record
     */
    public function update($id, array $data): Model;

    /**
     * Xóa record
     */
    public function delete($id): bool;

    /**
     * Đếm số records
     */
    public function count(): int;
}
