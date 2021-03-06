<?php

namespace Modules\Icommerceauthorize\Repositories\Cache;

use Modules\Icommerceauthorize\Repositories\IcommerceAuthorizeRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheIcommerceAuthorizeDecorator extends BaseCacheDecorator implements IcommerceAuthorizeRepository
{
    public function __construct(IcommerceAuthorizeRepository $icommerceauthorize)
    {
        parent::__construct();
        $this->entityName = 'icommerceauthorize.icommerceauthorizes';
        $this->repository = $icommerceauthorize;
    }

     /**
     * List or resources
     *
     * @return mixed
     */
    public function encriptUrl($parameters,$conf)
    {
        return $this->remember(function () use ($orderID,$transactionID,$currencyID) {
            return $this->repository->encriptUrl($orderID,$transactionID,$currencyID);
        });
    }


     /**
     * List or resources
     *
     * @return mixed
     */
    public function decriptUrl($eUrl)
    {
        return $this->remember(function () use ($eUrl) {
            return $this->repository->decriptUrl($eUrl);
        });
    }


}
