// jshint ignore:start
define(['report_adeptus_insights/vendor_lottie_light'], function(lottie) {
    'use strict';
    
    /**
     * Lottie library wrapper for Adeptus Insights
     * This provides a simple interface to the Lottie library
     */
    
    // Return the Lottie library directly
    if (typeof window !== 'undefined' && window.lottie) {
        return window.lottie;
    }
    
    // Fallback if window.lottie is not available
    return null;
});
