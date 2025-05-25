<?php
/**
 * 2007-2025 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2025 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DeliveryEstimate extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'deliveryestimate';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Simonas - simasak56@gmail.com';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans(
            'Delivery Estimate',
            [],
            'Modules.Deliveryestimate.Admin'
        );

        $this->description = $this->trans(
            'Displays estimated delivery date on the product page using the displayReassurance hook.',
            [],
            'Modules.Deliveryestimate.Admin'
        );

        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall this module?',
            [],
            'Modules.Deliveryestimate.Admin'
        );

        if (!Configuration::get('DELIVERYESTIMATE_ENABLED')) {
            $this->warning = $this->trans(
                'Delivery Estimate module is not configured yet.',
                [],
                'Modules.Deliveryestimate.Admin'
            );
        }

        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_,
        ];
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayReassurance')
            && Configuration::updateValue('DELIVERYESTIMATE_ENABLED', true)
            && Configuration::updateValue('DELIVERYESTIMATE_DAYS', 2)
            && Configuration::updateValue('DELIVERYESTIMATE_CUTOFF', '13:00')
            && Configuration::updateValue('DELIVERYESTIMATE_CUSTOM_TEXT', 'Numatomas pristatymo laikas')
            && Configuration::updateValue('DELIVERYESTIMATE_LIVE_MODE', false);
    }

    public function uninstall()
    {
        return Configuration::deleteByName('DELIVERYESTIMATE_ENABLED')
            && Configuration::deleteByName('DELIVERYESTIMATE_DAYS')
            && Configuration::deleteByName('DELIVERYESTIMATE_CUTOFF')
            && Configuration::deleteByName('DELIVERYESTIMATE_CUSTOM_TEXT')
            && parent::uninstall();
    }

    public function getContent(): string
    {
        if (Tools::isSubmit('submitDeliveryestimateModule')) {
            Configuration::updateValue('DELIVERYESTIMATE_ENABLED', (bool) Tools::getValue('DELIVERYESTIMATE_ENABLED'));
            Configuration::updateValue('DELIVERYESTIMATE_DAYS', (int) Tools::getValue('DELIVERYESTIMATE_DAYS'));
            Configuration::updateValue('DELIVERYESTIMATE_CUTOFF', Tools::getValue('DELIVERYESTIMATE_CUTOFF'));
            Configuration::updateValue('DELIVERYESTIMATE_CUSTOM_TEXT', Tools::getValue('DELIVERYESTIMATE_CUSTOM_TEXT'));

            $this->context->smarty->assign('confirmation', $this->l('Nustatymai sėkmingai išsaugoti.'));
        }

        $this->context->smarty->assign([
            'form' => $this->renderForm(),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }
    protected function renderForm(): string
    {
        $form = new HelperForm();
        $form->module = $this;
        $form->name_controller = $this->name;
        $form->token = Tools::getAdminTokenLite('AdminModules');
        $form->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        $fields_form = [[
            'form' => [
                'legend' => ['title' => $this->l('Settings')],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Rodyti blokelį'),
                        'name' => 'DELIVERYESTIMATE_ENABLED',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on', 'value' => 1, 'label' => $this->l('Taip')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->l('Ne')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Dienų rezervas'),
                        'name' => 'DELIVERYESTIMATE_DAYS',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Laiko riba (pvz. 13:00)'),
                        'name' => 'DELIVERYESTIMATE_CUTOFF',
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('Individualus tekstas'),
                        'name' => 'DELIVERYESTIMATE_CUSTOM_TEXT',
                        'desc' => $this->l('Šis tekstas bus rodomas virš pristatymo datos.'),
                    ],
                ],
                'submit' => [
                    'name' => 'submitDeliveryestimateModule',
                    'title' => $this->l('Išsaugoti'),
                ],
            ],
        ]];

        $form->fields_value = [
            'DELIVERYESTIMATE_ENABLED' => Configuration::get('DELIVERYESTIMATE_ENABLED'),
            'DELIVERYESTIMATE_DAYS' => Configuration::get('DELIVERYESTIMATE_DAYS'),
            'DELIVERYESTIMATE_CUTOFF' => Configuration::get('DELIVERYESTIMATE_CUTOFF'),
            'DELIVERYESTIMATE_CUSTOM_TEXT' => Configuration::get('DELIVERYESTIMATE_CUSTOM_TEXT'),
        ];

        return $form->generateForm($fields_form);
    }

    public function hookDisplayReassurance(array $params): string
    {
        if (!Configuration::get('DELIVERYESTIMATE_ENABLED')) {
            return '';
        }

        $daysReserve = (int) Configuration::get('DELIVERYESTIMATE_DAYS');
        $cutoff = Configuration::get('DELIVERYESTIMATE_CUTOFF');
        $now = new DateTime();

        list($h, $m) = explode(':', $cutoff);
        $cutoffTime = (clone $now)->setTime((int) $h, (int) $m);

        if ($now > $cutoffTime) {
            ++$daysReserve;
        }

        $deliveryDate = clone $now;
        while ($daysReserve > 0) {
            $deliveryDate->modify('+1 day');
            if (!in_array($deliveryDate->format('N'), [6, 7])) {
                --$daysReserve;
            }
        }

        $this->context->smarty->assign([
            'cutoff' => $cutoff,
            'delivery_date' => $deliveryDate->format('Y-m-d'),
            'before_cutoff' => $now <= $cutoffTime,
            'custom_text' => Configuration::get('DELIVERYESTIMATE_CUSTOM_TEXT'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/deliveryestimate.tpl');
    }

    public function hookDisplayHeader()
    {
        if ($this->context->controller->php_self === 'product') {
            $this->context->controller->addCSS($this->_path . 'views/css/front.css');
        }
    }
}
