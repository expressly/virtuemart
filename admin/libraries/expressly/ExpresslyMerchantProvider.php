<?php

use Expressly\Entity\Merchant;

/**
 *
 */
class ExpresslyMerchantProvider implements \Expressly\Provider\MerchantProviderInterface
{
    const API_KEY = 'expressly_api_key';
    const HOST    = 'expressly_host';
    const PATH    = 'expressly_path';

    /**
     * @var
     */
    private $merchant;

    /**
     *
     */
    public function __construct()
    {
        if (JComponentHelper::getParams('com_expressly')->get(self::PATH)) {
            $this->updateMerchant();
        }
    }

    /**
     *
     */
    private function updateMerchant()
    {
        $merchant = new Expressly\Entity\Merchant();
        $merchant
            ->setApiKey(JComponentHelper::getParams('com_expressly')->get(self::API_KEY))
            ->setHost(JComponentHelper::getParams('com_expressly')->get(self::HOST))
            ->setPath(JComponentHelper::getParams('com_expressly')->get(self::PATH));

        $this->merchant = $merchant;
    }

    /**
     * @param Merchant $merchant
     * @return $this
     */
    public function setMerchant(Merchant $merchant)
    {
        $this->update_params([
            'expressly_api_key' => $merchant->getApiKey(),
            'expressly_host'    => $merchant->getHost(),
            'expressly_path'    => $merchant->getPath(),
        ]);

        $this->merchant = $merchant;

        return $this;
    }

    /**
     * @param bool|false $update
     * @return Merchant
     */
    public function getMerchant($update = false)
    {
        if (!$this->merchant instanceof Merchant || $update) {
            $this->updateMerchant();
        }

        return $this->merchant;
    }

    protected function update_params(array $params)
    {
        $com_expressly = JComponentHelper::getParams('com_expressly');

        foreach ($params as $key => $value) {
            $com_expressly->set($key, $value);
        }

        $component_id = JComponentHelper::getComponent('com_expressly')->id;

        $table = JTable::getInstance('extension');
        $table->load($component_id);

        $table->bind(['params' => $com_expressly->toString()]);

        // check for error
        if (!$table->check()) {
            //$this->setError('lastcreatedate: check: ' . $table->getError());
            return false;
        }
        // Save to database
        if (!$table->store()) {
            //$this->setError('lastcreatedate: store: ' . $table->getError());
            return false;
        }

    }
}
