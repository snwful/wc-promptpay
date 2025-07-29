<?php
/**
 * PromptPay QR Code Generator
 *
 * @package PromptPay_N8N_Gateway
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PromptPay QR Code Generator Class
 */
class PromptPay_N8N_QR_Generator {

    /**
     * Generate PromptPay QR code data
     *
     * @param string $promptpay_id PromptPay ID (phone or national ID)
     * @param float $amount Payment amount
     * @return string QR code data
     */
    public function generate_qr_data( $promptpay_id, $amount ) {
        // Clean and format PromptPay ID
        $promptpay_id = $this->format_promptpay_id( $promptpay_id );
        
        // Format amount
        $amount = number_format( (float) $amount, 2, '.', '' );
        
        // Build EMV QR code data according to PromptPay specification
        $qr_data = '';
        
        // Payload Format Indicator
        $qr_data .= $this->build_tlv( '00', '01' );
        
        // Point of Initiation Method
        $qr_data .= $this->build_tlv( '01', '12' );
        
        // Merchant Account Information
        $merchant_info = '';
        $merchant_info .= $this->build_tlv( '00', 'A000000677010111' ); // PromptPay AID
        $merchant_info .= $this->build_tlv( '01', $promptpay_id );
        $qr_data .= $this->build_tlv( '29', $merchant_info );
        
        // Transaction Currency (THB = 764)
        $qr_data .= $this->build_tlv( '53', '764' );
        
        // Transaction Amount
        if ( $amount > 0 ) {
            $qr_data .= $this->build_tlv( '54', $amount );
        }
        
        // Country Code
        $qr_data .= $this->build_tlv( '58', 'TH' );
        
        // Merchant Name
        $qr_data .= $this->build_tlv( '59', 'PromptPay Payment' );
        
        // Merchant City
        $qr_data .= $this->build_tlv( '60', 'Bangkok' );
        
        // CRC placeholder
        $qr_data .= '6304';
        
        // Calculate CRC16
        $crc = $this->calculate_crc16( $qr_data );
        $qr_data .= strtoupper( dechex( $crc ) );
        
        return $qr_data;
    }

    /**
     * Generate QR code image URL using Google Charts API
     *
     * @param string $qr_data QR code data
     * @param int $size QR code size
     * @return string QR code image URL
     */
    public function generate_qr_code_url( $qr_data, $size = 300 ) {
        $encoded_data = urlencode( $qr_data );
        return "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl={$encoded_data}&choe=UTF-8";
    }

    /**
     * Generate QR code using PHP QR Code library (alternative method)
     *
     * @param string $qr_data QR code data
     * @param string $filename Output filename
     * @param int $size QR code size
     * @return string|false File path on success, false on failure
     */
    public function generate_qr_code_file( $qr_data, $filename = null, $size = 300 ) {
        // Check if QR code library is available
        if ( ! class_exists( 'QRcode' ) ) {
            // Try to include a simple QR code library or use alternative method
            return $this->generate_qr_code_svg( $qr_data, $filename );
        }

        if ( ! $filename ) {
            $filename = 'qr_' . md5( $qr_data ) . '.png';
        }

        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/promptpay-qr/';
        
        if ( ! file_exists( $qr_dir ) ) {
            wp_mkdir_p( $qr_dir );
        }

        $file_path = $qr_dir . $filename;
        
        try {
            QRcode::png( $qr_data, $file_path, QR_ECLEVEL_L, 8, 2 );
            return $upload_dir['baseurl'] . '/promptpay-qr/' . $filename;
        } catch ( Exception $e ) {
            error_log( 'QR Code generation failed: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Generate simple SVG QR code (fallback method)
     *
     * @param string $qr_data QR code data
     * @param string $filename Output filename
     * @return string|false File URL on success, false on failure
     */
    private function generate_qr_code_svg( $qr_data, $filename = null ) {
        // For production, you should use a proper QR code library
        // This is a simplified fallback that uses Google Charts API
        return $this->generate_qr_code_url( $qr_data );
    }

    /**
     * Format PromptPay ID
     *
     * @param string $promptpay_id Raw PromptPay ID
     * @return string Formatted PromptPay ID
     */
    private function format_promptpay_id( $promptpay_id ) {
        // Remove all non-numeric characters
        $promptpay_id = preg_replace( '/[^0-9]/', '', $promptpay_id );
        
        // Determine if it's a phone number or national ID
        if ( strlen( $promptpay_id ) === 10 && substr( $promptpay_id, 0, 1 ) === '0' ) {
            // Phone number: convert 0XXXXXXXXX to 66XXXXXXXXX
            return '66' . substr( $promptpay_id, 1 );
        } elseif ( strlen( $promptpay_id ) === 13 ) {
            // National ID: use as is
            return $promptpay_id;
        } elseif ( strlen( $promptpay_id ) === 9 && substr( $promptpay_id, 0, 2 ) === '66' ) {
            // Already formatted phone number
            return $promptpay_id;
        }
        
        // Return as is if format is unclear
        return $promptpay_id;
    }

    /**
     * Build TLV (Tag-Length-Value) format
     *
     * @param string $tag Tag
     * @param string $value Value
     * @return string TLV formatted string
     */
    private function build_tlv( $tag, $value ) {
        $length = str_pad( strlen( $value ), 2, '0', STR_PAD_LEFT );
        return $tag . $length . $value;
    }

    /**
     * Calculate CRC16 checksum
     *
     * @param string $data Data to calculate checksum for
     * @return int CRC16 checksum
     */
    private function calculate_crc16( $data ) {
        $crc = 0xFFFF;
        $polynomial = 0x1021;
        
        for ( $i = 0; $i < strlen( $data ); $i++ ) {
            $crc ^= ( ord( $data[ $i ] ) << 8 );
            
            for ( $j = 0; $j < 8; $j++ ) {
                if ( $crc & 0x8000 ) {
                    $crc = ( $crc << 1 ) ^ $polynomial;
                } else {
                    $crc = $crc << 1;
                }
                $crc &= 0xFFFF;
            }
        }
        
        return $crc;
    }

    /**
     * Validate PromptPay ID format
     *
     * @param string $promptpay_id PromptPay ID to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_promptpay_id( $promptpay_id ) {
        // Remove all non-numeric characters
        $clean_id = preg_replace( '/[^0-9]/', '', $promptpay_id );
        
        // Check for valid phone number (10 digits starting with 0, or 11 digits starting with 66)
        if ( preg_match( '/^0[0-9]{9}$/', $clean_id ) || preg_match( '/^66[0-9]{9}$/', $clean_id ) ) {
            return true;
        }
        
        // Check for valid national ID (13 digits)
        if ( preg_match( '/^[0-9]{13}$/', $clean_id ) ) {
            return $this->validate_national_id_checksum( $clean_id );
        }
        
        return false;
    }

    /**
     * Validate Thai national ID checksum
     *
     * @param string $national_id 13-digit national ID
     * @return bool True if valid checksum, false otherwise
     */
    private function validate_national_id_checksum( $national_id ) {
        if ( strlen( $national_id ) !== 13 ) {
            return false;
        }
        
        $sum = 0;
        for ( $i = 0; $i < 12; $i++ ) {
            $sum += (int) $national_id[ $i ] * ( 13 - $i );
        }
        
        $check_digit = ( 11 - ( $sum % 11 ) ) % 10;
        
        return $check_digit === (int) $national_id[12];
    }

    /**
     * Get QR code data for testing
     *
     * @param string $promptpay_id PromptPay ID
     * @param float $amount Amount
     * @return array QR code information
     */
    public function get_qr_info( $promptpay_id, $amount ) {
        $formatted_id = $this->format_promptpay_id( $promptpay_id );
        $qr_data = $this->generate_qr_data( $promptpay_id, $amount );
        
        return array(
            'original_id' => $promptpay_id,
            'formatted_id' => $formatted_id,
            'amount' => $amount,
            'qr_data' => $qr_data,
            'qr_url' => $this->generate_qr_code_url( $qr_data ),
            'is_valid' => $this->validate_promptpay_id( $promptpay_id )
        );
    }
}
