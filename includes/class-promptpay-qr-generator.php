<?php
/**
 * PromptPay QR Code Generator
 * 
 * Generates PromptPay QR codes for payments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PromptPay_QR_Generator {

    /**
     * Generate PromptPay QR code data
     * 
     * @param string $promptpay_id PromptPay ID (phone number or national ID)
     * @param float $amount Payment amount
     * @return string QR code data string
     */
    public static function generate_qr_data( $promptpay_id, $amount = 0 ) {
        // Clean PromptPay ID
        $promptpay_id = self::clean_promptpay_id( $promptpay_id );
        
        if ( empty( $promptpay_id ) ) {
            return false;
        }

        // Build EMV QR code data
        $qr_data = '';
        
        // Payload Format Indicator
        $qr_data .= self::build_tlv( '00', '01' );
        
        // Point of Initiation Method
        $qr_data .= self::build_tlv( '01', '12' );
        
        // Merchant Account Information
        $merchant_info = '';
        $merchant_info .= self::build_tlv( '00', 'A000000677010111' ); // Application Identifier
        $merchant_info .= self::build_tlv( '01', self::format_promptpay_id( $promptpay_id ) ); // PromptPay ID
        
        $qr_data .= self::build_tlv( '29', $merchant_info );
        
        // Transaction Currency (THB = 764)
        $qr_data .= self::build_tlv( '53', '764' );
        
        // Transaction Amount (if specified)
        if ( $amount > 0 ) {
            $qr_data .= self::build_tlv( '54', number_format( $amount, 2, '.', '' ) );
        }
        
        // Country Code
        $qr_data .= self::build_tlv( '58', 'TH' );
        
        // Merchant Name (optional)
        $qr_data .= self::build_tlv( '59', 'PromptPay Payment' );
        
        // Merchant City (optional)
        $qr_data .= self::build_tlv( '60', 'Bangkok' );
        
        // Calculate and append CRC
        $qr_data .= self::build_tlv( '63', self::calculate_crc( $qr_data . '6304' ) );
        
        return $qr_data;
    }

    /**
     * Generate QR code image URL
     * 
     * @param string $qr_data QR code data
     * @param int $size Image size in pixels
     * @return string QR code image URL
     */
    public static function generate_qr_image_url( $qr_data, $size = 300 ) {
        // Use Google Charts API for QR code generation
        $base_url = 'https://chart.googleapis.com/chart';
        $params = array(
            'chs' => $size . 'x' . $size,
            'cht' => 'qr',
            'chl' => urlencode( $qr_data ),
            'choe' => 'UTF-8'
        );
        
        return $base_url . '?' . http_build_query( $params );
    }

    /**
     * Clean PromptPay ID
     * 
     * @param string $promptpay_id Raw PromptPay ID
     * @return string Cleaned PromptPay ID
     */
    private static function clean_promptpay_id( $promptpay_id ) {
        // Remove all non-numeric characters
        $cleaned = preg_replace( '/[^0-9]/', '', $promptpay_id );
        
        // Validate length (phone: 10 digits, national ID: 13 digits)
        if ( strlen( $cleaned ) === 10 || strlen( $cleaned ) === 13 ) {
            return $cleaned;
        }
        
        return false;
    }

    /**
     * Format PromptPay ID for QR code
     * 
     * @param string $promptpay_id Cleaned PromptPay ID
     * @return string Formatted PromptPay ID
     */
    private static function format_promptpay_id( $promptpay_id ) {
        if ( strlen( $promptpay_id ) === 10 ) {
            // Phone number: add country code
            return '0066' . substr( $promptpay_id, 1 );
        } elseif ( strlen( $promptpay_id ) === 13 ) {
            // National ID: use as is
            return $promptpay_id;
        }
        
        return $promptpay_id;
    }

    /**
     * Build TLV (Tag-Length-Value) structure
     * 
     * @param string $tag Tag
     * @param string $value Value
     * @return string TLV string
     */
    private static function build_tlv( $tag, $value ) {
        $length = str_pad( strlen( $value ), 2, '0', STR_PAD_LEFT );
        return $tag . $length . $value;
    }

    /**
     * Calculate CRC-16 checksum
     * 
     * @param string $data Data to calculate CRC for
     * @return string 4-character hex CRC
     */
    private static function calculate_crc( $data ) {
        $crc = 0xFFFF;
        $polynomial = 0x1021;
        
        for ( $i = 0; $i < strlen( $data ); $i++ ) {
            $crc ^= ( ord( $data[$i] ) << 8 );
            
            for ( $j = 0; $j < 8; $j++ ) {
                if ( $crc & 0x8000 ) {
                    $crc = ( $crc << 1 ) ^ $polynomial;
                } else {
                    $crc = $crc << 1;
                }
                $crc &= 0xFFFF;
            }
        }
        
        return strtoupper( str_pad( dechex( $crc ), 4, '0', STR_PAD_LEFT ) );
    }

    /**
     * Validate PromptPay ID
     * 
     * @param string $promptpay_id PromptPay ID to validate
     * @return bool True if valid, false otherwise
     */
    public static function validate_promptpay_id( $promptpay_id ) {
        $cleaned = self::clean_promptpay_id( $promptpay_id );
        return $cleaned !== false;
    }
}
