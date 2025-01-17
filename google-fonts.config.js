        GOOGLE_FONT_CONFIG=$(cat <<EOF
        const path = require('path');
        module.exports = {
            fonts: [
                { family: 'Open Sans' },
                { family: 'Roboto' },
                { family: 'Lato', variants: ['400', '700'] },
            ],
            outputPath: '$TEMP_FONTS_DIR',
            outputFilename: 'fonts.css',
        };
        EOF
        )
