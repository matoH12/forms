FROM owasp/modsecurity-crs:nginx-alpine

# Custom nginx proxy configuration with proper buffer sizes for Inertia.js
# Overrides the default template to fix 502 errors caused by small proxy buffers
COPY default.conf.template \
     /etc/nginx/templates/conf.d/default.conf.template

# CRS exclusion rules - loaded BEFORE CRS rules
# Per-URI exclusions for fields that trigger false positives
COPY REQUEST-900-EXCLUSION-RULES-BEFORE-CRS.conf \
     /etc/modsecurity.d/owasp-crs/rules/REQUEST-900-EXCLUSION-RULES-BEFORE-CRS.conf

# CRS global exclusions - loaded AFTER CRS rules
# Global rule removals: _method (920650), _token, Inertia headers
COPY RESPONSE-999-EXCLUSION-RULES-AFTER-CRS.conf \
     /etc/modsecurity.d/owasp-crs/rules/RESPONSE-999-EXCLUSION-RULES-AFTER-CRS.conf
