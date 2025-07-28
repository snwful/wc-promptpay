<?php
namespace WooPromptPay\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PromptPay QR Code Generator
 * 
 * Static helper class for generating PromptPay QR codes
 */
class PP_QR_Generator {

    /**
     * Generate QR code data URI for PromptPay payment
     * 
     * @param string $promptpay_id PromptPay ID (phone/citizen ID/etc)
     * @param float  $amount       Payment amount
     * @return string|false        Data URI or false on failure
     */
    public static function generate_qr_data_uri( $promptpay_id, $amount = 0.0 ) {
        if ( empty( $promptpay_id ) ) {
            return false;
        }
        
        // Generate PromptPay payload
        $payload = self::generate_promptpay_payload( $promptpay_id, $amount );
        
        // Try to use QR code library if available
        if ( self::is_qr_library_available() ) {
            return self::generate_qr_with_library( $payload );
        }
        
        // Fallback to Google Charts API
        return self::generate_qr_with_google_charts( $payload );
    }
    
    /**
     * Generate PromptPay payload string
     * 
     * @param string $promptpay_id PromptPay ID
     * @param float  $amount       Payment amount
     * @return string              PromptPay payload
     */
    private static function generate_promptpay_payload( $promptpay_id, $amount ) {
        // Format PromptPay ID
        $formatted_id = self::format_promptpay_id( $promptpay_id );
        
        // Build payload components
        $payload = '';
        
        // Payload Format Indicator (Tag 00)
        $payload .= self::build_tlv( '00', '01' );
        
        // Point of Initiation Method (Tag 01) - Static QR
        $payload .= self::build_tlv( '01', '11' );
        
        // Merchant Account Information (Tag 29)
        $merchant_info = '';
        $merchant_info .= self::build_tlv( '00', 'A000000677010111' ); // PromptPay Application ID
        $merchant_info .= self::build_tlv( '01', $formatted_id );
        $payload .= self::build_tlv( '29', $merchant_info );
        
        // Transaction Currency (Tag 53) - THB
        $payload .= self::build_tlv( '53', '764' );
        
        // Transaction Amount (Tag 54) - if amount is specified
        if ( $amount > 0 ) {
            $payload .= self::build_tlv( '54', number_format( $amount, 2, '.', '' ) );
        }
        
        // Country Code (Tag 58)
        $payload .= self::build_tlv( '58', 'TH' );
        
        // Merchant Name (Tag 59) - optional
        $site_name = get_bloginfo( 'name' );
        if ( $site_name ) {
            $payload .= self::build_tlv( '59', substr( $site_name, 0, 25 ) );
        }
        
        // Merchant City (Tag 60) - optional
        $payload .= self::build_tlv( '60', 'Bangkok' );
        
        // CRC (Tag 63) - calculated last
        $payload .= '6304';
        $crc = self::calculate_crc16( $payload );
        $payload = substr( $payload, 0, -4 ) . strtoupper( dechex( $crc ) );
        
        return $payload;
    }
    
    /**
     * Format PromptPay ID based on type
     * 
     * @param string $id PromptPay ID
     * @return string    Formatted ID
     */
    private static function format_promptpay_id( $id ) {
        // Remove all non-numeric characters first
        $cleaned_id = preg_replace( '/[^0-9]/', '', $id );
        
        // Detect ID type and format accordingly
        if ( strlen( $cleaned_id ) === 10 && substr( $cleaned_id, 0, 1 ) === '0' ) {
            // Mobile number: convert 0XXXXXXXXX to +66XXXXXXXXX
            return '+66' . substr( $cleaned_id, 1 );
        } elseif ( strlen( $cleaned_id ) === 13 ) {
            // Citizen ID or Tax ID: use as is
            return $cleaned_id;
        } else {
            // Use original input for other formats (e-wallet, etc.)
            return $id;
        }
    }
    
    /**
     * Build TLV (Tag-Length-Value) format
     * 
     * @param string $tag   Tag
     * @param string $value Value
     * @return string       TLV string
     */
    private static function build_tlv( $tag, $value ) {
        $length = str_pad( strlen( $value ), 2, '0', STR_PAD_LEFT );
        return $tag . $length . $value;
    }
    
    /**
     * Calculate CRC16 checksum
     * 
     * @param string $data Input data
     * @return int         CRC16 checksum
     */
    private static function calculate_crc16( $data ) {
        $crc = 0xFFFF;
        $polynomial = 0x1021;
        
        for ( $i = 0; $i < strlen( $data ); $i++ ) {
            $crc ^= ( ord( $data[ $i ] ) << 8 );
            
            for ( $j = 0; $j < 8; $j++ ) {
                if ( $crc & 0x8000 ) {
                    $crc = ( ( $crc << 1 ) ^ $polynomial ) & 0xFFFF;
                } else {
                    $crc = ( $crc << 1 ) & 0xFFFF;
                }
            }
        }
        
        return $crc;
    }
    
    /**
     * Check if QR code library is available
     * 
     * @return bool True if library is available
     */
    private static function is_qr_library_available() {
        // Check for phpqrcode library
        if ( class_exists( 'QRcode' ) ) {
            return true;
        }
        
        // Try to include phpqrcode if file exists
        $qr_lib_path = WPPN8N_DIR . 'vendor/phpqrcode/qrlib.php';
        if ( file_exists( $qr_lib_path ) ) {
            require_once $qr_lib_path;
            return class_exists( 'QRcode' );
        }
        
        return false;
    }
    
    /**
     * Generate QR code using phpqrcode library
     * 
     * @param string $payload PromptPay payload
     * @return string|false   Data URI or false on failure
     */
    private static function generate_qr_with_library( $payload ) {
        try {
            // Generate QR code in memory
            ob_start();
            \QRcode::png( $payload, null, QR_ECLEVEL_M, 8, 2 );
            $qr_image = ob_get_clean();
            
            if ( $qr_image ) {
                return 'data:image/png;base64,' . base64_encode( $qr_image );
            }
        } catch ( Exception $e ) {
            error_log( 'PromptPay QR generation error: ' . $e->getMessage() );
        }
        
        return false;
    }
    
    /**
     * Generate QR code using Google Charts API (fallback)
     * 
     * @param string $payload PromptPay payload
     * @return string         Google Charts QR code URL
     */
    private static function generate_qr_with_google_charts( $payload ) {
        $base_url = 'https://chart.googleapis.com/chart';
        $params = [
            'cht' => 'qr',
            'chs' => '300x300',
            'chl' => $payload,
            'choe' => 'UTF-8'
        ];
        
        return $base_url . '?' . http_build_query( $params );
    }
    
    /**
     * Validate PromptPay ID format
     * 
     * @param string $id PromptPay ID
     * @return bool      True if valid format
     */
    public static function validate_promptpay_id( $id ) {
        if ( empty( $id ) ) {
            return false;
        }
        
        // Remove all non-numeric characters for validation
        $cleaned_id = preg_replace( '/[^0-9]/', '', $id );
        
        // Check for valid formats
        if ( strlen( $cleaned_id ) === 10 && substr( $cleaned_id, 0, 1 ) === '0' ) {
            // Mobile number format: 0XXXXXXXXX
            return true;
        } elseif ( strlen( $cleaned_id ) === 13 ) {
            // Citizen ID or Tax ID format: 13 digits
            return true;
        } elseif ( strlen( $id ) >= 5 && strlen( $id ) <= 50 ) {
            // Other formats (e-wallet, etc.)
            return true;
        }
        
        return false;
    }
    
    /**
     * Get PromptPay ID type
     * 
     * @param string $id PromptPay ID
     * @return string    ID type (mobile, citizen, tax, other)
     */
    public static function get_promptpay_id_type( $id ) {
        $cleaned_id = preg_replace( '/[^0-9]/', '', $id );
        
        if ( strlen( $cleaned_id ) === 10 && substr( $cleaned_id, 0, 1 ) === '0' ) {
            return 'mobile';
        } elseif ( strlen( $cleaned_id ) === 13 ) {
            return 'citizen_or_tax';
        } else {
            return 'other';
        }
    }
}
