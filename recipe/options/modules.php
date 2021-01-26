<?php

namespace Deployer;

require_once CUSTOM_RECIPE_DIR . '/options/packages.php';

option(
    'modules',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
    'Modules to enabling. --modules=TM_AjaxPro,TM_Askit'
);

set('option_modules', function () {
    $modules = [];
    if (input()->hasOption('modules')
        && !empty(input()->getOption('modules'))
    ) {
        $modules = input()->getOption('modules');

        if (empty($modules)) {
            $modules = [];
            $packages = get('option_packages_filtred');

            $getModulesbyPackages = function ($packages) {

                if (!function_exists('getPackages')) {
                    function getPackages($lab = 'tm')
                    {
                        if ('tm' == $lab) {
                            $lab = 'tmhub';
                        }
                        // if (!isset($this->json[$lab])) {
                            $url = "https://$lab.github.io/packages/";
                            if ($lab === 'swissup') {
                                $url = 'https://docs.swissuplabs.com/packages/';
                            }
                            // $url = 'https://tmhub.github.io/packages/';
                            $includes = json_decode(@file_get_contents($url . 'packages.json'), true);
                            if (!isset($includes['includes'])) {
                                writeln("<error>{$url}packages.json is not available</error>");
                                die;
                            }
                            $include = current(array_keys($includes['includes']));
                            $packages = json_decode(@file_get_contents($url . $include), true);
                            $packages = $packages['packages'];

                            return $packages;
                            // $this->json[$lab] = $packages;
                        // }

                        // return $this->json[$lab];
                    }
                }
                if (!function_exists('getReqires')) {
                    function getReqires($packageName, $lab = 'tm')
                    {
                        $requires = [];
                        if (is_array($packageName)) {
                            foreach ($packageName as &$_packageName) {
                                // $_requires = $this->getReqires($package, $lab);
                                $_requires = getReqires($_packageName, $lab);
                                $requires = array_merge($requires, $_requires);
                                list($_packageName,) = explode(':', $_packageName);
                            }
                            $requires = array_merge($requires, $packageName);
                            $requires = array_unique($requires);
                        } else {
                            if (!strstr($packageName, '/')) {
                                return $requires;
                            }
                            list($vendor, $module) = explode('/', $packageName);
                            if (!in_array($vendor, array('tm', 'swissup'))) {
                                return $requires;
                            }
                            $lab = $vendor = strtolower($vendor);
                            list($packageName,) = explode(':', $packageName);
                            // $packages = $this->getPackages($lab);
                            $packages = getPackages($vendor);
                            // Zend_Debug::dump($packages);
                            if (!isset($packages[$packageName])) {
                                return $requires;
                            }
                            $p = $packages[$packageName];

                            $p = isset($p['dev-master']) ? $p['dev-master'] : current($p);

                            if (isset($p['require'])) {
                                $requires = array_keys($p['require']);
                                $rs = $requires;
                                foreach ($rs as $r) {
                                    // $_requires = $this->getReqires($r, $lab);
                                    $_requires = getReqires($r, $lab);
                                    $requires = array_merge($requires, $_requires);
                                }
                                $requires = array_merge($requires, [$packageName]);
                                $requires = array_unique($requires);
                            };
                        }
                        return $requires;
                    }
                }
                // print_r($packages);
                $packages = getReqires($packages);
                // print_r($packages);
                $a = array(
                    'tm/abandoned'             => 'TM_Abandoned',
                    'tm/address-autocomplete'  => 'TM_AddressAutocomplete',
                    'tm/amp'                   => 'TM_Amp',
                    'tm/ajax-layered-navigation' => 'TM_AjaxLayeredNavigation',
                    'tm/ajax-pro'              => 'TM_AjaxPro',
                    'tm/ajax-search'           => 'TM_AjaxSearch',
                    'tm/akismet'               => 'TM_Akismet',
                    'tm/argento'               => 'TM_Argento',
                    'tm/argento_argentotheme'  => 'TM_ArgentoArgentotheme',
                    'tm/argento_mage2cloud'    => 'TM_ArgentoMage2Cloud',
                    'tm/argento_swissup'       => 'TM_ArgentoSwissup',
                    'tm/argento_tm'            => 'TM_ArgentoTM',
                    'tm/ask-it'                => 'TM_AskIt',
                    'tm/attributepages'        => 'TM_Attributepages',
                    'tm/cache'                 => array('TM_Cache', 'TM_Crawler'),
                    'tm/catalog-configurable-swatches' => 'TM_CatalogConfigurableSwatches',
                    'tm/cdn'                   => 'TM_CDN',
                    'tm/core'                  => 'TM_Core',
                    'tm/countdowntimer'        => 'TM_CountdownTimer',
                    'tm/dailydeals'            => array('TM_DailyDeals', 'TM_CountdownTimer'),
                    'tm/downloadable'          => 'TM_Downloadable',
                    'tm/easy-banner'           => 'TM_EasyBanner',
                    'tm/easycatalogimg'        => 'TM_EasyCatalogImg',
                    'tm/easycolorswatches'     => 'TM_EasyColorSwatches',
                    'tm/easyflags'             => 'TM_EasyFlags',
                    'tm/easylightbox'          => 'TM_EasyLightbox',
                    'tm/easynavigation'        => 'TM_EasyNavigation',
                    'tm/easyslide'             => 'TM_Easyslide',
                    'tm/easytabs'              => 'TM_EasyTabs',
                    'tm/email'                 => 'TM_Email',
                    'tm/facebooklb'            => 'TM_FacebookLB', //FaceBookLB
                    'tm/firecheckout'          => array('TM_FireCheckout', 'TM_CheckoutFields' , 'TM_CheckoutSuccess'),
                    'tm/checkout-success'      => 'TM_CheckoutSuccess',
                    'tm/helpdesk'              => array('TM_Helpmate', 'TM_KnowledgeBase'),
                    'tm/knowledge-base'        => 'TM_KnowledgeBase',
                    'tm/highlight'             => 'TM_Highlight',
                    'tm/license'               => 'TM_License',
                    'tm/lightboxpro'           => 'TM_LightboxPro',
                    'tm/mobileswitcher'        => 'TM_MobileSwitcher',
                    'tm/navigation-pro'        => 'TM_NavigationPro',
                    'tm/newsletterbooster'     => array('TM_NewsletterBooster', 'TM_SegmentationSuite'),
                    'tm/notifier'              => 'TM_Notifier',
                    'tm/orderattachment'       => 'TM_OrderAttachment',
                    'tm/pagespeed'             => 'TM_Pagespeed',
                    'tm/pro-labels'            => 'TM_ProLabels',
                    'tm/prozoom'               => 'TM_Prozoom',
                    'tm/purify'                => 'TM_Purify',
                    'tm/productvideos'         => 'TM_ProductVideos',
                    'tm/quickshopping'         => 'TM_QuickShopping',
                    'tm/review-reminder'       => 'TM_ReviewReminder',
                    'tm/recaptcha'             => 'TM_Recaptcha',
                    'tm/recurring'             => 'TM_Recurring',
                    'tm/reward'                => 'TM_Reward',
                    'tm/richsnippets'          => 'TM_RichSnippets',
                    'tm/secure'                => array('TM_BigBrother', 'TM_TwoFactorAuthentication'),
                    'tm/smartsuggest'          => 'TM_SmartSuggest',
                    'tm/sold-together'         => 'TM_SoldTogether',
                    'tm/socialsuite'           => 'TM_SocialSuite',
                    'tm/subscription'          => 'TM_Subscription',
                    'tm/subscription-checker'  => 'TM_SubscriptionChecker',
                    'tm/suggestpage'           => 'TM_SuggestPage',
                    'tm/templatef001'          => array('TM_Ajax', 'TM_Featured', 'TM_Templatef001'),
                    'tm/templatef002'          => 'TM_Templatef002',
                    'tm/templatem001'          => 'TM_Templatem001',
                    'tm/testimonials'          => 'TM_Testimonials',
                    'tm/demo-deployer'         => 'TM_Deployer',
                    // 'tm/botprotection'         => 'TM_BotProtection',
                    'tm/bot-protection'        => 'TM_BotProtection',
                    'tm/affiliate-suite'       => 'TM_AffiliateSuite',
                    'tm/ga-plugin-scrolldepth' => 'TM_GaPluginScrolldepth',

                    'swissup/prolabels'      => 'Swissup_ProLabels',
                    'swissup/reviewreminder' => 'Swissup_Reviewreminder',
                    'swissup/maintenance'    => 'Swissup_Maintenance',
                    'swissup/email'          => 'Swissup_Email',
                    'swissup/askit'          => 'Swissup_Askit',
                    'swissup/testimonials'   => 'Swissup_Testimonials',
                    'swissup/highlight'      => 'Swissup_Highlight',
                    'swissup/core'           => 'Swissup_Core',
                    'swissup/countdowntimer' => 'Swissup_Countdowntimer',
                    'swissup/address-autocomplete' => 'Swissup_AddressAutocomplete',
                    'swissup/taxvat'         => 'Swissup_Taxvat',
                    'swissup/ajaxpro'        => array('Swissup_Ajaxpro', 'Swissup_Suggestpage'),
                    'swissup/checkout-success' => 'Swissup_CheckoutSuccess',
                    'swissup/sold-together'  => 'Swissup_SoldTogether',
                    'swissup/firecheckout'   => 'Swissup_Firecheckout',
                    'swissup/orderattachment' => 'Swissup_Orderattachment',
                    'swissup/geoip'          => 'Swissup_Geoip',
                    'swissup/akismet'        => 'Swissup_Akismet',
                    'swissup/ajaxsearch'     => 'Swissup_Ajaxsearch',
                    'swissup/easycatalogimg' => 'Swissup_Easycatalogimg',
                    'swissup/attributepages' => 'Swissup_Attributepages',
                    'swissup/easy-slide'     => 'Swissup_EasySlide',
                    'swissup/pro-labels'     => 'Swissup_ProLabels',
                    'swissup/easybanner'     => 'Swissup_Easybanner',
                    'swissup/easytabs'       => 'Swissup_Easytabs',
                    'swissup/subscription-checker' => 'Swissup_SubscriptionChecker',
                    'swissup/fblike'         => 'Swissup_Fblike',
                    'swissup/rich-snippets'  => 'Swissup_RichSnippets',
                    'swissup/easyflags'      => 'Swissup_Easyflags',
                    'swissup/slick-carousel' => 'Swissup_SlickCarousel',
                    'swissup/dailydeals'     => 'Swissup_Dailydeals',
                    'swissup/font-awesome'   => 'Swissup_FontAwesome',
                    'swissup/suggestpage'    => 'Swissup_Suggestpage'
                );
                if (is_string($packages)) {
                    $packages = array($packages);
                }

                $res = array();
                foreach ($packages as $p) {
                    list($p,) = explode(':', $p);
                    if (isset($a[$p])) {
                        $res[] = $a[$p];
                    } else {
                        if (!strstr($p, '/')) {
                            continue;
                        }
                        list($vendor, $module) = explode('/', $p);
                        $vendor = strtolower($vendor);
                        if (!in_array($vendor, array('tm', 'swissup'))) {
                            continue;
                        }
                        if ($vendor === 'swissup' && strpos($module, 'module-') !== 0) {
                            continue;
                        }
                        if ('tm' == $vendor) {
                            $vendor = 'TM';
                        } else {
                            $vendor = 'Swissup';
                        }

                        $module = str_replace('module-', '', $module);
                        $module = str_replace(' ', '', ucwords(str_replace('_', ' ', $module)));
                        $module = str_replace(' ', '', ucwords(str_replace('-', ' ', $module)));
                        $res[] = $vendor . "_" . $module;
                    }
                }

                if (!function_exists('array_flatten')) {
                    function array_flatten($array)
                    {
                        $return = array();
                        foreach ($array as $key => $value) {
                            if (is_array($value)) {
                                $return = array_merge($return, array_flatten($value));
                            } else {
                                $return[] = $value;
                            }
                        }

                        return $return;
                    }
                }
                $res = array_values($res);
                $res = array_flatten($res);
                $res = array_unique($res);
                return $res;
            };
            $modules = $getModulesbyPackages($packages);
        } else {
            $modules = explode(',', $modules);
        }
    }
    return array_filter($modules);
});

task('debug:option:modules', function () {
    $modules = get('option_modules');
    print_r($modules);
});
