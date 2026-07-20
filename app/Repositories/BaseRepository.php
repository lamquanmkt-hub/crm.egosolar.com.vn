<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;

/**
 * Repository cơ sở cung cấp các thao tác CRUD dùng chung cho Eloquent model.
 */
abstract class BaseRepository
{
    protected Model $model;

    /**
     * Lấy tất cả bản ghi của model.
     */
    public function all()
    {
        return $this->model->all();
    }

    /**
     * Tìm bản ghi theo ID.
     */
    public function find($id)
    {
        return $this->model->find($id);
    }

    /**
     * Tạo mới bản ghi.
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Cập nhật bản ghi theo ID.
     */
    public function update($id, array $data)
    {
        $model = $this->find($id);
        $model->update($data);

        return $model;
    }

    /**
     * Xoá bản ghi theo ID.
     */
    public function delete($id)
    {
        return $this->find($id)->delete();
    }
}
