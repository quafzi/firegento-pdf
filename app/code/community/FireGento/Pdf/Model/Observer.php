<?php
/**
 * This file is part of the FIREGENTO project.
 *
 * FireGento_GermanSetup is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 3 as
 * published by the Free Software Foundation.
 *
 * This script is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * PHP version 5
 *
 * @category  FireGento
 * @package   FireGento_Pdf
 * @author    FireGento Team <team@firegento.com>
 * @copyright 2012 FireGento Team (http://www.firegento.de). All rights served.
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @version   $Id:$
 * @since     0.1.0
 */
/**
 * FireGento Pdf observer.
 *
 * @category  FireGento
 * @package   FireGento_Pdf
 * @author    FireGento Team <team@firegento.com>
 * @copyright 2012 FireGento Team (http://www.firegento.de). All rights served.
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @version   $Id:$
 * @since     0.1.0
 */
class FireGento_Pdf_Model_Observer
{
    /**
     * Add notes to invoice document.
     *
     * @param Varien_Event_Observer $observer
     * @return FireGento_Pdf_Model_Observer
     */
    public function addInvoiceNotes(Varien_Event_Observer $observer)
    {
        $this->addInvoiceMaturity($observer);
        $this->addPaymentMethod($observer);
        return $this;
    }

    /**
     * Add maturity to invoice notes.
     *
     * @param Varien_Event_Observer $observer
     * @return FireGento_Pdf_Model_Observer
     */
    public function addInvoiceMaturity(Varien_Event_Observer $observer)
    {
        $result = $observer->getResult();
        $notes = $result->getNotes();

        $maturity = Mage::getStoreConfig('sales_pdf/invoice/maturity');
        if (!empty($maturity) || 0 < $maturity) {
            $maturity = Mage::helper('firegento_pdf')->__('Invoice maturity: %s days', Mage::getStoreConfig('sales_pdf/invoice/maturity'));
        } elseif ('0' === $maturity) {
            $maturity = Mage::helper('firegento_pdf')->__('Invoice is payable immediately');
        }

        $notes[] = $maturity;
        $result->setNotes($notes);
        return $this;
    }

    /**
     * Add payment method to invoice notes.
     *
     * @param Varien_Event_Observer $observer
     * @return FireGento_Pdf_Model_Observer
     */
    public function addPaymentMethod(Varien_Event_Observer $observer)
    {
        $result = $observer->getResult();
        $notes = $result->getNotes();
        if ((bool)(int) Mage::getStoreConfig('sales_pdf/invoice/verbose_payment_info')) {
            $notes = array_merge($notes, $this->getPaymentInformation($observer->getOrder()));
        } else {
            $notes[] = Mage::helper('firegento_pdf')->__(
                'Payment method: %s',
                $observer->getOrder()->getPayment()->getMethodInstance()->getTitle()
            );
        }
        $result->setNotes($notes);
        return $this;
    }

    protected function getPaymentInformation($order)
    {
        /* Payment */
        $paymentInfo = Mage::helper('payment')->getInfoBlock($order->getPayment())
            ->setIsSecureMode(true)
            ->toHtml();
        $paymentInfo = Mage::helper('firegento_pdf')->__('Payment method: %s', $paymentInfo);
        $paymentInfo = preg_split('/<br[^>]*>/i', htmlspecialchars_decode($paymentInfo, ENT_QUOTES));

        $notes = array();
        foreach ($paymentInfo as $value){
            if (trim($value) == '') {
                continue;
            }
            // add "Payment Method" lines
            foreach (Mage::helper('core/string')->str_split($value, 65, true, true) as $_value) {
                $notes[] = strip_tags(trim($_value));
            }
        }
        return $notes;
    }
}