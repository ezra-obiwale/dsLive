<?php

namespace DSLive\Services;

use DScribe\Core\AService,
    DScribe\Core\IModel,
    Exception,
    Object;

abstract class SuperService extends AService {

    protected $form;

    /**
     * @todo
     * @var array
     */
    protected $errors;

    /**
     * Initialize property $errors to empty array
     */
    protected function init() {
        $this->errors = array();
    }

    private function getDefaultFormName() {
        return (class_exists($this->getModule() . '\Forms\\' . $this->getClassName() . 'Form')) ?
                $this->getModule() . '\Forms\\' . $this->getClassName() . 'Form' : null;
    }

    /**
     * Allows public access to form
     * @return \DScibe\Form\Form
     */
    public function getForm() {
        if (!$this->form) {
            if ($defaultFormName = $this->getDefaultFormName())
                $this->form = new $defaultFormName;
        }

        return $this->form;
    }

    /**
     * Fetches all data in the database
     * return array
     */
    public function fetchAll() {
        return $this->repository->fetchAll();
    }

    /**
     * Finds a row from database
     * @param mixed $id Id to fetch with
     * @return mixed
     */
    public function findOneBy($column, $value, $exception = true) {
        $model = $this->repository->findOneBy($column, $value);
        if (!$model && $exception)
            throw new Exception('Required page was not found');
        $this->model = $model;
        return $this->model;
    }

    /**
     * Finds a row from database
     * @param mixed $id Id to fetch with
     * @return mixed
     */
    public function findOne($id, $exception = true) {
        $model = $this->repository->findOne($id);
        if (!$model && $exception)
            throw new Exception('Required page was not found');
        $this->model = $model;
        return $this->model;
    }

    /**
     * Finds a row from database with the given criteria
     * @param array $criteria
     * @return mixed
     */
    public function findOneWhere($criteria, $exception = true) {
        $model = $this->repository->findOneWhere($criteria);
        if (!$model && $exception)
            throw new Exception('Required page was not found');
        $this->model = $model;
        return $this->model;
    }

    /**
     * Inserts data into the database
     * @param IModel $model
     * @param Object $files
     * @return boolean
     * @todo Set first parameter as form so one can fetch either model or data
     */
    public function create(IModel $model, Object $files = null, $flush = true) {
        if (method_exists($model, 'uploadFiles') && !$model->uploadFiles($files))
            return false;

        if ($this->repository->insert($model)->execute()) {
            if ($flush)
                $this->flush();

            return $model;
        }

        return false;
    }

    /**
     * Saves data into the database
     * @param IModel $model
     * @param Object $files
     * @return boolean
     * @todo Set first parameter as form so one can fetch either model or data
     */
    public function save(IModel $model, Object $files = null, $flush = true) {
        if (!is_object($files)) {
            $files = new \Object();
        }
        $_files = array_values($files->toArray());
        if ($files->count() && ((is_array($_files[0]->error) && $_files[0]->error[0] !== UPLOAD_ERR_NO_FILE) || (!is_array($_files[0]->error) && $_files[0]->error !== UPLOAD_ERR_NO_FILE)) && method_exists($model, 'uploadFiles')) {
            if (method_exists($model, 'unlink')) {
                foreach ($files->toArray() as $name => $content) {
                    $method = 'get' . $name;
                    $model->unlink($model->$method());
                }
            }
            if (!$model->uploadFiles($files))
                return false;
        }
        if ($this->repository->update($model)->execute()) {
            if ($flush)
                $this->flush();

            return $model;
        }

        return false;
    }

    /**
     * Deletes data from the database
     * return boolean
     */
    public function delete($flush = true) {
        try {
            //@todo find a way to delete attached files
            $deleted = $this->repository->delete($this->model)->execute();
            if ($flush) {
                return $this->flush();
            }
            else {
                return $deleted;
            }
        }
        catch (Exception $ex) {
            if (stristr($ex->getMessage(), 'Integrity constraint violation:')) {
                $this->errors[] = ucwords(str_replace('_', ' ', $this->model->getTableName())) .
                        ' is being used in another part of the application';
            }
            return false;
        }
    }

    public function upsert(IModel $model, $where = 'id', $flush = true) {
        if ($this->repository->upsert(array($model), $where)->execute()) {
            return ($flush) ? $this->flush() : true;
        }
    }

}
