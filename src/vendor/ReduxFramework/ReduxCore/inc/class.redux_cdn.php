<?php

    /**
     * Redux Framework CDN Container Class
     *
     * @author      Kevin Provance (kprovance)
     * @package     Redux_Framework
     * @subpackage  Core
     */

// Exit if accessed directly
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    if ( ! class_exists( 'Redux_CDN' ) ) {
        class Redux_CDN {
            static public $_parent;
            static private $_set;

            private static function is_enqueued( $handle, $is_script, $list = 'enqueued' ) {
                if ( $is_script ) {
                    wp_script_is( $handle, $list );
                } else {
                    wp_style_is( $handle, $list );
                }
            }

            private static function _register( $handle, $src_cdn, $deps, $ver, $footer_or_media, $is_script = true ) {
                if ( $is_script ) {
                    wp_register_script( $handle, $src_cdn, $deps, $ver, $footer_or_media );
                } else {
                    wp_register_style( $handle, $src_cdn, $deps, $ver, $footer_or_media );
                }
            }

            private static function _enqueue( $handle, $src_cdn, $deps, $ver, $footer_or_media, $is_script = true ) {
                if ( $is_script ) {
                    wp_enqueue_script( $handle, $src_cdn, $deps, $ver, $footer_or_media );
                } else {
                    wp_enqueue_style( $handle, $src_cdn, $deps, $ver, $footer_or_media );
                }
            }

            private static function _cdn( $handle, $src_cdn, $deps, $ver, $footer_or_media, $register = true, $is_script = true ) {
                $tran_key = '_style_cdn_is_up';
                if ( $is_script ) {
                    $tran_key = '_script_cdn_is_up';
                }

                $cdn_is_up = get_transient( $handle . $tran_key );
                if ( $cdn_is_up ) {
                    if ( $register ) {
                        self::_register( $handle, $src_cdn, $deps, $ver, $footer_or_media, $is_script );
                    } else {
                        self::_enqueue( $handle, $src_cdn, $deps, $ver, $footer_or_media, $is_script );
                    }
                } else {

                    $prefix       = $src_cdn[1] == "/" ? 'http:' : '';
                    $cdn_response = @wp_remote_get( $prefix . $src_cdn );

                    if ( is_wp_error( $cdn_response ) || wp_remote_retrieve_response_code( $cdn_response ) != '200' ) {
                        if ( class_exists( 'Redux_VendorURL' ) ) {
                            $src = Redux_VendorURL::get_url( $handle );

                            if ( $register ) {
                                self::_register( $handle, $src, $deps, $ver, $footer_or_media, $is_script );
                            } else {
                                self::_enqueue( $handle, $src, $deps, $ver, $footer_or_media, $is_script );
                            }
                        } else {
                            if ( ! self::is_enqueued( $handle, 'enqueued', $is_script ) ) {
                                $msg = __( 'Please wait a few minutes, then try refreshing the page. Unable to load some remotely hosted scripts.', 'redux-framework' );
                                if ( self::$_parent->args['dev_mode'] ) {
                                    $msg = sprintf( __( 'If you are developing offline, please download and install the <a href="%s" target="_blank">Redux Vendor Support</a> plugin/extension to bypass the our CDN and avoid this warning', 'redux-framework' ), 'https://github.com/reduxframework/redux-vendor-support' );
                                }

                                $msg = '<strong>' . __( 'Redux Framework Warning', 'redux-framework' ) . '</strong><br/>' . sprintf( __( '%s CDN unavailable.  Some controls may not render properly.', 'redux-framework' ), $handle ) . '  ' . $msg;

                                $data = array(
                                    'parent'    => self::$_parent,
                                    'type'      => 'error',
                                    'msg'       => $msg,
                                    'id'        => $handle . $tran_key,
                                    'dismiss'   => false
                                );

                                Redux_Admin_Notices::set_notice($data);
                            }
                        }
                    } else {
                        set_transient( $handle . $tran_key, true, MINUTE_IN_SECONDS * self::$_parent->args['cdn_check_time'] );

                        if ( $register ) {
                            self::_register( $handle, $src_cdn, $deps, $ver, $footer_or_media, $is_script );
                        } else {
                            self::_enqueue( $handle, $src_cdn, $deps, $ver, $footer_or_media, $is_script );
                        }
                    }
                }
            }

            private static function _vendor_plugin( $handle, $src_cdn, $deps, $ver, $footer_or_media, $register = true, $is_script = true ) {
                if ( class_exists( 'Redux_VendorURL' ) ) {
                    $src = Redux_VendorURL::get_url( $handle );

                    if ( $register ) {
                        self::_register( $handle, $src, $deps, $ver, $footer_or_media, $is_script );
                    } else {
                        self::_enqueue( $handle, $src, $deps, $ver, $footer_or_media, $is_script );
                    }
                } else {
                    if ( ! self::$_set ) {
                        $msg = sprintf( __( 'The <a href="%s">Vendor Support plugin</a> (or extension) is either not installed or not activated and thus, some controls may not render properly.  Please ensure that it is installed and <a href="%s">activated</a>', 'redux-framework' ), 'https://github.com/reduxframework/redux-vendor-support', admin_url( 'plugins.php' ) );

                        $data = array(
                            'parent'    => self::$_parent,
                            'type'      => 'error',
                            'msg'       => $msg,
                            'id'        => $handle,
                            'dismiss'   => false
                        );

                        Redux_Admin_Notices::set_notice($data);

                        self::$_set = true;
                    }
                }
            }

            public static function register_style( $handle, $src_cdn = false, $deps = array(), $ver = false, $media = 'all' ) {
                if ( self::$_parent->args['use_cdn'] ) {
                    self::_cdn( true, $handle, $src_cdn, $deps, $ver, $media, $is_script = false );
                } else {
                    self::_vendor_plugin( true, $handle, $src_cdn, $deps, $ver, $media, $is_script = false );
                }
            }

            public static function register_script( $handle, $src_cdn = false, $deps = array(), $ver = false, $in_footer = false ) {
                if ( self::$_parent->args['use_cdn'] ) {
                    self::_cdn( true, $handle, $src_cdn, $deps, $ver, $in_footer, $is_script = true );
                } else {
                    self::_vendor_plugin( true, $handle, $src_cdn, $deps, $ver, $in_footer, $is_script = true );
                }
            }

            public static function enqueue_style( $handle, $src_cdn = false, $deps = array(), $ver = false, $media = 'all' ) {
                if ( self::$_parent->args['use_cdn'] ) {
                    self::_cdn( false, $handle, $src_cdn, $deps, $ver, $media, $is_script = false );
                } else {
                    self::_vendor_plugin( false, $handle, $src_cdn, $deps, $ver, $media, $is_script = false );
                }
            }

            public static function enqueue_script( $handle, $src_cdn = false, $deps = array(), $ver = false, $in_footer = false ) {
                if ( self::$_parent->args['use_cdn'] ) {
                    self::_cdn( false, $handle, $src_cdn, $deps, $ver, $in_footer, $is_script = true );
                } else {
                    self::_vendor_plugin( false, $handle, $src_cdn, $deps, $ver, $in_footer, $is_script = true );
                }
            }
        }
    }
