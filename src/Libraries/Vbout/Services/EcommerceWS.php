<?php


namespace App\Libraries\Vbout\Services;


use App\Libraries\Vbout\Vbout;
use App\Libraries\Vbout\VboutException;
use App\Models\Setting;

class EcommerceWS extends Vbout
{
    protected function init()
    {
        $this->api_url = '/ecommerce/';
    }

    //New Functions
    public function Customer($data, $action)
    {
        $result = array();
        try {
            $this->set_method('POST');
            if ($action == 1 )
                $insertRecord = $this->upsertCustomer($data);
            else if($action == 2)
                $insertRecord = $this->editCustomer($data);
            else $result = "Error with Action taken.";

            if ($insertRecord != null && isset($insertRecord['data'])) {
                $result = $insertRecord['data'];
            }
        } catch (VboutException $ex) {
            $result = $ex->getData();
        }
        return $result;
    }
    public function Cart($data, $action)
    {
        $result = array();
        try {
            $this->set_method('POST');
            if ($action == 1 )
                $insertRecord = $this->CreateCart($data);
            if ($insertRecord != null && isset($insertRecord['data'])) {
                $result = $insertRecord['data'];
            }

        } catch (VboutException $ex) {
            $result = $ex->getData();
        }

        return $result;
    }
    public function CartItem($data, $action)
    {

        $result = array();

        try {
            $this->set_method('POST');
            if ($action == 1 )
                $insertRecord = $this->AddCartItem($data);
            else if($action == 2 )
                $insertRecord = $this->CreateCart($data);
            else $insertRecord = $this->RemoveCartItem($data);

            if ($insertRecord != null && isset($insertRecord['data'])) {
                $result = $insertRecord['data'];
            }
        } catch (VboutException $ex) {
            $result = $ex->getData();
        }

        return $result;
    }
    public function Order($data, $action)
    {

        $result = array();
        try {
            $this->set_method('POST');
            if ($action == 1 )
                $insertRecord = $this->createOrder($data);
            elseif ($action == 2 )
                $insertRecord = $this->updateorder($data);
            else $result = "Error";

            if ($insertRecord != null && isset($insertRecord['data'])) {
                $result = $insertRecord['data'];
            }
        } catch (VboutException $ex) {
            $result = $ex->getData();
        }

        return $result;
    }
    public function Product($data, $action)
    {
        $result = array();
        try {
            $this->set_method('POST');
            if ($action == 1 )
                $insertRecord = $this->addProductView($data);
            else $result = "Error";

            if ($insertRecord != null && isset($insertRecord['data'])) {
                $result = $insertRecord['data'];
            }

        } catch (VboutException $ex) {
            $result = $ex->getData();
        }

        return $result;
    }
    public function Category($data, $action)
    {
        $result = array();
        try {
            $this->set_method('POST');
            if ($action == 1 )
                $insertRecord = $this->AddCategoryView($data);
            else $result = "Error";

            if ($insertRecord != null && isset($insertRecord['data'])) {
                $result = $insertRecord['data'];
            }

        } catch (VboutException $ex) {
            $result = $ex->getData();
        }

        return $result;
    }

    public function sendProductSearch($data)
    {
        $result = array();
        try {
            $this->set_method('POST');
            $insertRecord = $this->AddProductSearch($data);

            if ($insertRecord != null && isset($insertRecord['data'])) {
                $result = $insertRecord['data'];
            }
        } catch (VboutException $ex) {
            $result = $ex->getData();
        }

        return $result;
    }
    public function getDomain($data)
    {
        $result = array();
        try {
            $this->set_method('POST');
             $insertRecord = $this->GetVBTDomain($data);
             if ($insertRecord != null && isset($insertRecord['data'])) {
                    $result = $insertRecord['data'];
             }
        } catch (VboutException $ex) {
            $result = $ex->getData();
        }

        return $result;
    }
    public function sendSettingsSync($data)
    {
        $result = array();
        try {
            $this->set_method('POST');
            $insertRecord = $this->updateSettings($data);

            if ($insertRecord != null && isset($insertRecord['data'])) {
                $result = $insertRecord['data'];

            }

        } catch (VboutException $ex) {
            $result = $ex->getData();
        }

        return $result;
    }
    public function sendAPIIntegrationCreation($data,$action =1)
    {
        $result = array();
        try {
            $this->set_method('POST');
            if ($action ==1)
                $insertRecord = $this->createIntegration($data);
            else if ($action == 3)
                $insertRecord = $this->removeSettings($data);

            if ($insertRecord != null && isset($insertRecord['data'])) {
                $result = $insertRecord['data'];
            }
        } catch (VboutException $ex) {
            $result = $ex->getData();
        }

        return $result;
    }
}