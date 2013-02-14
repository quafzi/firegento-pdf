<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Bundle
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Sales Order Invoice Pdf default items renderer
 *
 * @category   Mage
 * @package    Mage_Sales
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class FireGento_Pdf_Model_Items_Bundle extends Mage_Bundle_Model_Sales_Order_Pdf_Items_Invoice
{
    /**
     * Draw item line
     *
     */
    public function draw($position = 1)
    {
        $order  = $this->getOrder();
        $item   = $this->getItem();
        $pdf    = $this->getPdf();
        $page   = $this->getPage();

        $fontSize = 9;

        $this->_setFontRegular();
        $items = $this->getChilds($item);

        $_prevOptionId = '';
        $drawItems = array();

        foreach ($items as $_item) {
            $line   = array();

            $attributes = $this->getSelectionAttributes($_item);
            if (is_array($attributes)) {
                $optionId   = $attributes['option_id'];
            }
            else {
                $optionId = 0;
            }

            // draw SKUs
            if (!$_item->getOrderItem()->getParentItem()) {
                // start new line with position number
                $line[] = array(
                    'text'  => $position,
                    'feed'  => $pdf->margin['left'] + 10,
                    'align' => 'right',
                    'font_size' => $fontSize
                );

                $text = array();
                foreach (Mage::helper('core/string')->str_split($item->getSku(), 17) as $part) {
                    $text[] = $part;
                }
                $line[] = array(
                    'text'  => $text,
                    'feed'  => $pdf->margin['left'] + 25,
                );
            }

            if (!isset($drawItems[$optionId])) {
                $drawItems[$optionId] = array(
                    'lines'  => array(),
                    'height' => 15
                );
            }

            if ($_item->getOrderItem()->getParentItem()) {
                if ($_prevOptionId != $attributes['option_id']) {
                    // first column should be empty
                    $line[] = array(
                        'text'  => '',
                        'feed'  => $pdf->margin['left'] + 10,
                        'align' => 'right',
                        'font_size' => $fontSize
                    );
                    $line[] = array(
                        'text'  => '',
                        'feed'  => $pdf->margin['left'] + 25,
                        'align' => 'right',
                        'font_size' => $fontSize
                    );
                    $line[] = array(
                        'font'  => 'italic',
                        'text'  => Mage::helper('core/string')->str_split($attributes['option_label'], 45, true, true),
                        'align' => 'right',
                        'feed'  => $pdf->margin['left'] + 110
                    );

                    $drawItems[$optionId] = array(
                        'lines'  => array($line),
                        'height' => 15
                    );

                    $line = array();

                    $_prevOptionId = $attributes['option_id'];
                }
            }

            /* in case Product name is longer than 80 chars - it is written in a few lines */
            if ($_item->getOrderItem()->getParentItem()) {
                $name = $this->getValueHtml($_item);
            } else {
                $name = $_item->getName();
            }
            $line[] = array(
                'text'  => Mage::helper('core/string')->str_split($name, 45, true, true),
                'feed'  => $pdf->margin['left'] + 120
            );

            // draw prices
            if ($this->canShowPriceInfo($_item)) {
                $columns = array();
                // prepare qty
                $columns['qty'] = array(
                    'text'  => $item->getQty() * 1,
                    'align' => 'right',
                    'font_size' => $fontSize,
                    '_width' => 40
                );

                // prepare price
                $columns['price'] = array(
                    'text'  => $order->formatPriceTxt($item->getPrice()),
                    'align' => 'right',
                    'font_size' => $fontSize,
                    '_width' => 60
                );

                // prepare price_incl_tax
                $columns['price_incl_tax'] = array(
                    'text'  => $order->formatPriceTxt($item->getPriceInclTax()),
                    'align' => 'right',
                    'font_size' => $fontSize,
                    '_width' => 60
                );

                // prepare tax
                $columns['tax'] = array(
                    'text'  => $order->formatPriceTxt($item->getTaxAmount()),
                    'align' => 'right',
                    'font_size' => $fontSize,
                    '_width' => 50
                );

                // prepare tax_rate
                $columns['tax_rate'] = array(
                    'text'  => round($item->getOrderItem()->getTaxPercent(), 2) . '%',
                    'align' => 'right',
                    'font_size' => $fontSize,
                    '_width' => 50
                );

                // prepare subtotal
                $columns['subtotal'] = array(
                    'text' => $order->formatPriceTxt($item->getPrice() * $item->getQty() * 1),
                    'align' => 'right',
                    'font_size' => $fontSize,
                    '_width' => 50
                );

                // prepare subtotal_incl_tax
                $columns['subtotal_incl_tax'] = array(
                    'text' => $order->formatPriceTxt(($item->getPrice() * $item->getQty() * 1) + $item->getTaxAmount()),
                    'align' => 'right',
                    'font_size' => $fontSize,
                    '_width' => 70
                );

                // draw columns in specified order
                $columnsOrder = explode(',', Mage::getStoreConfig('sales_pdf/invoice/item_price_column_order'));
                // draw starting from right
                $columnsOrder = array_reverse($columnsOrder);
                $columnOffset = 0;
                foreach ($columnsOrder as $columnName) {
                    $columnName = trim($columnName);
                    if (array_key_exists($columnName, $columns)) {
                        $column = $columns[$columnName];
                        $column['feed'] = $pdf->margin['right'] - $columnOffset;
                        $columnOffset += $column['_width'];
                        unset($column['_width']);
                        $line[] = $column;
                    }
                }

                if (Mage::getStoreConfig('sales_pdf/invoice/show_item_discount')
                    && 0 < $item->getDiscountAmount()
                ) {
                    // print discount
                    $text = Mage::helper('firegento_pdf')->__(
                        'You get a discount of %s.',
                        $order->formatPriceTxt($item->getDiscountAmount())
                    );
                    $line[] = array(
                        'text'      => $text,
                        'align'     => 'right',
                        'feed'      => $pdf->margin['right'] - $columnOffset
                    );
                }
                /*
                $price = $order->formatPriceTxt($_item->getPrice());
                $line[] = array(
                    'text'  => $price,
                    'feed'  => 395,
                    'font'  => 'bold',
                    'align' => 'right'
                );
                $line[] = array(
                    'text'  => $_item->getQty()*1,
                    'feed'  => 435,
                    'font'  => 'bold',
                );

                $tax = $order->formatPriceTxt($_item->getTaxAmount());
                $line[] = array(
                    'text'  => $tax,
                    'feed'  => 495,
                    'font'  => 'bold',
                    'align' => 'right'
                );

                $row_total = $order->formatPriceTxt($_item->getRowTotal());
                $line[] = array(
                    'text'  => $row_total,
                    'feed'  => 565,
                    'font'  => 'bold',
                    'align' => 'right'
                );
                */
            }

            $drawItems[$optionId]['lines'][] = $line;
        }

        // custom options
        $options = $item->getOrderItem()->getProductOptions();
        if ($options) {
            if (isset($options['options'])) {
                foreach ($options['options'] as $option) {
                    $lines = array();
                    $lines[][] = array(
                        'text'  => Mage::helper('core/string')->str_split(strip_tags($option['label']), 40, true, true),
                        'font'  => 'italic',
                        'feed'  => 35
                    );

                    if ($option['value']) {
                        $text = array();
                        $_printValue = isset($option['print_value'])
                            ? $option['print_value']
                            : strip_tags($option['value']);
                        $values = explode(', ', $_printValue);
                        foreach ($values as $value) {
                            foreach (Mage::helper('core/string')->str_split($value, 30, true, true) as $_value) {
                                $text[] = $_value;
                            }
                        }

                        $lines[][] = array(
                            'text'  => $text,
                            'feed'  => 40
                        );
                    }

                    $drawItems[] = array(
                        'lines'  => $lines,
                        'height' => 15
                    );
                }
            }
        }

        $page = $pdf->drawLineBlocks($page, $drawItems, array('table_header' => true));

        $this->setPage($page);
    }
}
