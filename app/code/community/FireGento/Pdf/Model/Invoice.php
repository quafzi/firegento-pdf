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
 * Invoice model rewrite.
 *
 * @category  FireGento
 * @package   FireGento_Pdf
 * @author    FireGento Team <team@firegento.com>
 * @copyright 2012 FireGento Team (http://www.firegento.de). All rights served.
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @version   $Id:$
 * @since     0.1.0
 */
class FireGento_Pdf_Model_Invoice extends FireGento_Pdf_Model_Abstract
{
    public $encoding;
    public $pagecounter;

    public function __construct()
    {
        parent::__construct();
        $this->setMode('invoice');
    }

    /**
     * Return PDF document
     *
     * @param  array $invoices
     * @return Zend_Pdf
     */
    public function getPdf($invoices = array())
    {
        $engine = Mage::getStoreConfig('sales_pdf/invoice/engine');

        if ($engine) {
            // Check if chosen engine is not the class we are currently in.
            if (Mage::getConfig()->getModelClassName($engine) != __CLASS__) {
                $pdf = Mage::getModel($engine);

                if ($pdf && $pdf->test()) {
                    $pdf = $pdf->getPdf($invoices);
                    $this->_prepareDownloadResponse('invoice' . Mage::getSingleton('core/date')->date('Y-m-d_H-i-s') .
                        '.pdf', $pdf->render(), 'application/pdf');

                    return $pdf;

                } else {
                    $engine = false;
                }
            }
        }

        if (!$engine) {
            // Fallback to Magento standard invoice layout.
            $invoiceInstance = new Mage_Sales_Model_Order_Pdf_Invoice();
            $pdf = $invoiceInstance->getPdf($invoices);
            return $pdf;
        }

        $this->_beforeGetPdf();
        $this->_initRenderer('invoice');

        $mode = $this->getMode();

        $pdf = new Zend_Pdf();
        $this->_setPdf($pdf);

        $style = new Zend_Pdf_Style();
        $this->_setFontBold($style, 10);

        $this->pagecounter = 1;

        foreach ($invoices as $invoice) {
            if ($invoice->getStoreId()) {
                Mage::app()->getLocale()->emulate($invoice->getStoreId());
                Mage::app()->setCurrentStore($invoice->getStoreId());
            }
            $page = $pdf->newPage(Zend_Pdf_Page::SIZE_A4);
            $pdf->pages[] = $page;

            $order = $invoice->getOrder();

            /* add logo */
            $this->insertLogo($page, $invoice->getStore());

            /* add billing address */
            $this->y = 692;
            $this->insertBillingAddress($page, $order);

            // Add sender address
            $this->y = 705;
            $this->_insertSenderAddessBar($page);

            if ((bool)(int) Mage::getStoreConfig('sales_pdf/invoice/show_shipping_address')) {
                /* add shipping address */
                $this->y = 705;
                $this->insertShippingAddress($page, $order);
            }

            /* add header */
            $this->y = 592;
            $this->insertHeader($page, $order, $invoice);

            // Add footer
            $this->_addFooter($page, $invoice->getStore());

            /* add table header */
            $this->_setFontRegular($page, 9);
            $this->y = 562;
            $this->insertTableHeader($page);

            $this->y -=20;

            $position = 0;

            foreach ($invoice->getAllItems() as $item) {
                if ($item->getOrderItem()->getParentItem()) {
                    continue;
                }

                if ($this->y < 205) {
                    $page = $this->newPage(array());
                }

                $position++;
                $page = $this->_drawItem($item, $page, $order, $position);
            }
            
            /* add line after items */
            $page->drawLine($this->margin['left'], $this->y + 5, $this->margin['right'], $this->y + 5);

            /* add totals */
            $page = $this->insertTotals($page, $invoice);

            /* add note */
            if ($mode == 'invoice') {
                $this->insertNote($page, $order, $invoice);
            }
        }

        $this->_afterGetPdf();

        return $pdf;
    }

    /**
     * Insert Notice after Totals
     *
     * @param Zend_Pdf_Page $page Current Page Object of Zend_PDF
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @return void
     */
    protected function insertNote($page, &$order, &$invoice)
    {
        $fontSize = 10;
        $font = $this->_setFontRegular($page, $fontSize);
        $this->y = $this->y - 60;

        $notes = array();

        $result = new Varien_Object();
        $result->setNotes($notes);
        Mage::dispatchEvent('firegento_pdf_invoice_insert_note', array('order' => $order, 'invoice' => $invoice, 'result' => $result));
        $notes = array_merge($notes, $result->getNotes());

        $notes[] = Mage::helper('firegento_pdf')->__('Invoice date is equal to delivery date.');

        // Get free text notes.
        $note = Mage::getStoreConfig('sales_pdf/invoice/note');
        if (!empty($note)) {
            $tmpNotes = explode("\n", $note);
            $notes = array_merge($notes, $tmpNotes);
        }

        // Draw notes on invoice.
        foreach ($notes as $note) {
            // prepare the text so that it fits to the paper
            $note = $this->_prepareText($note, $page, $font, $fontSize);
            $tmpNotes = explode("\n", $note);
            foreach ($tmpNotes as $tmpNote) {
                $page->drawText($tmpNote, $this->margin['left'], $this->y + 30, $this->encoding);
                $this->Ln(15);
            }
        }
    }

    /**
     * Insert Table Header for Items
     *
     * @param Zend_Pdf_Page $page  Current Page Object of Zend_PDF
     *
     * @return void
     */
    protected function insertTableHeader(&$page)
    {
        $page->setFillColor($this->colors['grey1']);
        $page->setLineColor($this->colors['grey1']);
        $page->setLineWidth(1);
        $page->drawRectangle($this->margin['left'], $this->y, $this->margin['right'], $this->y - 15);

        $page->setFillColor($this->colors['black']);
        $font = $this->_setFontRegular($page, 9);

        $this->y -= 11;
        $page->drawText(Mage::helper('firegento_pdf')->__('Pos'),             $this->margin['left'] + 3,         $this->y, $this->encoding);
        $page->drawText(Mage::helper('firegento_pdf')->__('No.'),             $this->margin['left'] + 25,     $this->y, $this->encoding);
        $page->drawText(Mage::helper('firegento_pdf')->__('Description'),     $this->margin['left'] + 120,     $this->y, $this->encoding);

        $columns = array();
        $columns['price'] = array(
            'label'  => Mage::helper('firegento_pdf')->__('Price'),
            '_width' => 60
        );
        $columns['price_incl_tax'] = array(
            'label'  => Mage::helper('firegento_pdf')->__('Price (incl. tax)'),
            '_width' => 60
        );
        $columns['qty'] = array(
            'label'  => Mage::helper('firegento_pdf')->__('Qty'),
            '_width' => 40
        );
        $columns['tax'] = array(
            'label'  => Mage::helper('firegento_pdf')->__('Tax'),
            '_width' => 50
        );
        $columns['tax_rate'] = array(
            'label'  => Mage::helper('firegento_pdf')->__('Tax rate'),
            '_width' => 50
        );
        $columns['subtotal'] = array(
            'label'  => Mage::helper('firegento_pdf')->__('Total'),
            '_width' => 50
        );
        $columns['subtotal_incl_tax'] = array(
            'label'  => Mage::helper('firegento_pdf')->__('Total (incl. tax)'),
            '_width' => 70
        );

        // draw price, tax, and subtotal in specified order
        $columnsOrder = explode(',', Mage::getStoreConfig('sales_pdf/invoice/item_price_column_order'));
        // draw starting from right
        $columnsOrder = array_reverse($columnsOrder);
        $columnOffset = 0;
        foreach ($columnsOrder as $columnName) {
            $columnName = trim($columnName);
            if (array_key_exists($columnName, $columns)) {
                $column = $columns[$columnName];
                $labelWidth = $this->widthForStringUsingFontSize($column['label'], $font, 9);
                $page->drawText(
                    $column['label'],
                    $this->margin['right'] - $columnOffset - $labelWidth,
                    $this->y,
                    $this->encoding
                );
                $columnOffset += $column['_width'];
            }
        }
    }

    /**
     * Generate new PDF Page
     *
     * @param array $setting   page settings
     * @return object $page    PDF page object
     */
    public function newPage(array $settings = array())
    {
        $pdf = $this->_getPdf();

        $page = $pdf->newPage(Zend_Pdf_Page::SIZE_A4);
        $pdf->pages[] = $page;

        if ($this->imprint) {
            $this->y = 100;
            $this->_insertFooter($page);
        }

        $this->pagecounter++;
        $this->y = 110;
        $this->_insertPageCounter($page);

        $this->y = 800;
        $this->_setFontRegular($page, 9);

        return $page;
    }

    /**
     * 
     *
     * @param object $page     Current Page Object of Zend_PDF
     * @param array  $draw     
     * @param array  $pageSettings     
     *
     * @return object $page  PDF Page Object
     */
    public function drawLineBlocks(Zend_Pdf_Page $page, array $draw, array $pageSettings = array())
    {
        foreach ($draw as $itemsProp) {
            if (!isset($itemsProp['lines']) || !is_array($itemsProp['lines'])) {
                Mage::throwException(Mage::helper('sales')->__('Invalid draw line data. Please define "lines" array'));
            }
            $lines  = $itemsProp['lines'];
            $height = isset($itemsProp['height']) ? $itemsProp['height'] : 10;

            if (empty($itemsProp['shift'])) {
                $shift = 0;
                foreach ($lines as $line) {
                    $maxHeight = 0;
                    foreach ($line as $column) {
                        $lineSpacing = !empty($column['height']) ? $column['height'] : $height;
                        if (!is_array($column['text'])) {
                            $column['text'] = array($column['text']);
                        }
                        $top = 0;
                        foreach ($column['text'] as $part) {
                            $top += $lineSpacing;
                        }

                        $maxHeight = $top > $maxHeight ? $top : $maxHeight;
                    }
                    $shift += $maxHeight;
                }
                $itemsProp['shift'] = $shift;
            }

            if ($this->y - $itemsProp['shift'] < 200) {
                $page = $this->newPage($pageSettings);
            }

            foreach ($lines as $line) {
                $maxHeight = 0;
                foreach ($line as $column) {
                    $fontSize  = empty($column['font_size']) ? 7 : $column['font_size'];
                    if (!empty($column['font_file'])) {
                        $font = Zend_Pdf_Font::fontWithPath($column['font_file']);
                        $page->setFont($font, $fontSize);
                    }
                    else {
                        $fontStyle = empty($column['font']) ? 'regular' : $column['font'];
                        switch ($fontStyle) {
                            case 'bold':
                                $font = $this->_setFontBold($page, $fontSize);
                                break;
                            case 'italic':
                                $font = $this->_setFontItalic($page, $fontSize);
                                break;
                            default:
                                $font = $this->_setFontRegular($page, $fontSize);
                                break;
                        }
                    }

                    if (!is_array($column['text'])) {
                        $column['text'] = array($column['text']);
                    }

                    $lineSpacing = !empty($column['height']) ? $column['height'] : $height;
                    $top = 0;
                    foreach ($column['text'] as $part) {
                        $feed = $column['feed'];
                        $textAlign = empty($column['align']) ? 'left' : $column['align'];
                        $width = empty($column['width']) ? 0 : $column['width'];
                        switch ($textAlign) {
                            case 'right':
                                if ($width) {
                                    $feed = $this->getAlignRight($part, $feed, $width, $font, $fontSize);
                                }
                                else {
                                    $feed = $feed - $this->widthForStringUsingFontSize($part, $font, $fontSize);
                                }
                                break;
                            case 'center':
                                if ($width) {
                                    $feed = $this->getAlignCenter($part, $feed, $width, $font, $fontSize);
                                }
                                break;
                        }
                        $page->drawText($part, $feed, $this->y-$top, 'UTF-8');
                        $top += $lineSpacing;
                    }

                    $maxHeight = $top > $maxHeight ? $top : $maxHeight;
                }
                $this->y -= $maxHeight;
            }
        }

        return $page;
    }

    /**
     * Return status of the engine.
     *
     * @return bool
     */
    public function test()
    {
        return true;
    }

    /**
     * Initialize renderer process.
     *
     * @param string $type
     * @return void
     */
    protected function _initRenderer($type)
    {
        parent::_initRenderer($type);

        $this->_renderers['default'] = array(
            'model' => 'firegento_pdf/items_default',
            'renderer' => null
        );
        $this->_renderers['grouped'] = array(
            'model' => 'firegento_pdf/items_grouped',
            'renderer' => null
        );
    }
}

