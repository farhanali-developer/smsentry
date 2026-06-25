<?php
defined( 'ABSPATH' ) || exit;

/**
 * Static country / dial-code dataset used to render the country selector
 * shown next to phone number fields (profile setup, admin test SMS).
 */
class SMSentry_Countries {

	/**
	 * @return array<string, array{0: string, 1: string}> ISO2 code => [name, dial code without "+"]
	 */
	public static function all(): array {
		return array(
			'US' => array( 'United States', '1' ),
			'CA' => array( 'Canada', '1' ),
			'GB' => array( 'United Kingdom', '44' ),
			'AU' => array( 'Australia', '61' ),
			'DE' => array( 'Germany', '49' ),
			'FR' => array( 'France', '33' ),
			'IT' => array( 'Italy', '39' ),
			'ES' => array( 'Spain', '34' ),
			'PT' => array( 'Portugal', '351' ),
			'NL' => array( 'Netherlands', '31' ),
			'BE' => array( 'Belgium', '32' ),
			'CH' => array( 'Switzerland', '41' ),
			'AT' => array( 'Austria', '43' ),
			'SE' => array( 'Sweden', '46' ),
			'NO' => array( 'Norway', '47' ),
			'DK' => array( 'Denmark', '45' ),
			'FI' => array( 'Finland', '358' ),
			'IS' => array( 'Iceland', '354' ),
			'IE' => array( 'Ireland', '353' ),
			'PL' => array( 'Poland', '48' ),
			'CZ' => array( 'Czech Republic', '420' ),
			'SK' => array( 'Slovakia', '421' ),
			'HU' => array( 'Hungary', '36' ),
			'RO' => array( 'Romania', '40' ),
			'BG' => array( 'Bulgaria', '359' ),
			'GR' => array( 'Greece', '30' ),
			'HR' => array( 'Croatia', '385' ),
			'RS' => array( 'Serbia', '381' ),
			'SI' => array( 'Slovenia', '386' ),
			'BA' => array( 'Bosnia and Herzegovina', '387' ),
			'ME' => array( 'Montenegro', '382' ),
			'MK' => array( 'North Macedonia', '389' ),
			'AL' => array( 'Albania', '355' ),
			'XK' => array( 'Kosovo', '383' ),
			'LT' => array( 'Lithuania', '370' ),
			'LV' => array( 'Latvia', '371' ),
			'EE' => array( 'Estonia', '372' ),
			'UA' => array( 'Ukraine', '380' ),
			'BY' => array( 'Belarus', '375' ),
			'MD' => array( 'Moldova', '373' ),
			'RU' => array( 'Russia', '7' ),
			'KZ' => array( 'Kazakhstan', '7' ),
			'GE' => array( 'Georgia', '995' ),
			'AM' => array( 'Armenia', '374' ),
			'AZ' => array( 'Azerbaijan', '994' ),
			'TR' => array( 'Turkey', '90' ),
			'CY' => array( 'Cyprus', '357' ),
			'MT' => array( 'Malta', '356' ),
			'LU' => array( 'Luxembourg', '352' ),
			'MC' => array( 'Monaco', '377' ),
			'LI' => array( 'Liechtenstein', '423' ),
			'AD' => array( 'Andorra', '376' ),
			'SM' => array( 'San Marino', '378' ),
			'VA' => array( 'Vatican City', '39' ),
			'IN' => array( 'India', '91' ),
			'PK' => array( 'Pakistan', '92' ),
			'BD' => array( 'Bangladesh', '880' ),
			'LK' => array( 'Sri Lanka', '94' ),
			'NP' => array( 'Nepal', '977' ),
			'BT' => array( 'Bhutan', '975' ),
			'MV' => array( 'Maldives', '960' ),
			'AF' => array( 'Afghanistan', '93' ),
			'CN' => array( 'China', '86' ),
			'JP' => array( 'Japan', '81' ),
			'KR' => array( 'South Korea', '82' ),
			'KP' => array( 'North Korea', '850' ),
			'TW' => array( 'Taiwan', '886' ),
			'HK' => array( 'Hong Kong', '852' ),
			'MO' => array( 'Macau', '853' ),
			'MN' => array( 'Mongolia', '976' ),
			'VN' => array( 'Vietnam', '84' ),
			'TH' => array( 'Thailand', '66' ),
			'MY' => array( 'Malaysia', '60' ),
			'SG' => array( 'Singapore', '65' ),
			'ID' => array( 'Indonesia', '62' ),
			'PH' => array( 'Philippines', '63' ),
			'KH' => array( 'Cambodia', '855' ),
			'LA' => array( 'Laos', '856' ),
			'MM' => array( 'Myanmar', '95' ),
			'BN' => array( 'Brunei', '673' ),
			'TL' => array( 'Timor-Leste', '670' ),
			'NZ' => array( 'New Zealand', '64' ),
			'FJ' => array( 'Fiji', '679' ),
			'PG' => array( 'Papua New Guinea', '675' ),
			'SB' => array( 'Solomon Islands', '677' ),
			'VU' => array( 'Vanuatu', '678' ),
			'NC' => array( 'New Caledonia', '687' ),
			'PF' => array( 'French Polynesia', '689' ),
			'WS' => array( 'Samoa', '685' ),
			'TO' => array( 'Tonga', '676' ),
			'KI' => array( 'Kiribati', '686' ),
			'TV' => array( 'Tuvalu', '688' ),
			'NR' => array( 'Nauru', '674' ),
			'PW' => array( 'Palau', '680' ),
			'FM' => array( 'Micronesia', '691' ),
			'MH' => array( 'Marshall Islands', '692' ),
			'CK' => array( 'Cook Islands', '682' ),
			'NU' => array( 'Niue', '683' ),
			'TK' => array( 'Tokelau', '690' ),
			'WF' => array( 'Wallis and Futuna', '681' ),
			'NF' => array( 'Norfolk Island', '672' ),
			'IL' => array( 'Israel', '972' ),
			'PS' => array( 'Palestine', '970' ),
			'JO' => array( 'Jordan', '962' ),
			'LB' => array( 'Lebanon', '961' ),
			'SY' => array( 'Syria', '963' ),
			'IQ' => array( 'Iraq', '964' ),
			'IR' => array( 'Iran', '98' ),
			'SA' => array( 'Saudi Arabia', '966' ),
			'AE' => array( 'United Arab Emirates', '971' ),
			'QA' => array( 'Qatar', '974' ),
			'BH' => array( 'Bahrain', '973' ),
			'KW' => array( 'Kuwait', '965' ),
			'OM' => array( 'Oman', '968' ),
			'YE' => array( 'Yemen', '967' ),
			'EG' => array( 'Egypt', '20' ),
			'LY' => array( 'Libya', '218' ),
			'TN' => array( 'Tunisia', '216' ),
			'DZ' => array( 'Algeria', '213' ),
			'MA' => array( 'Morocco', '212' ),
			'SD' => array( 'Sudan', '249' ),
			'SS' => array( 'South Sudan', '211' ),
			'ET' => array( 'Ethiopia', '251' ),
			'ER' => array( 'Eritrea', '291' ),
			'DJ' => array( 'Djibouti', '253' ),
			'SO' => array( 'Somalia', '252' ),
			'KE' => array( 'Kenya', '254' ),
			'UG' => array( 'Uganda', '256' ),
			'TZ' => array( 'Tanzania', '255' ),
			'RW' => array( 'Rwanda', '250' ),
			'BI' => array( 'Burundi', '257' ),
			'CD' => array( 'Congo (DRC)', '243' ),
			'CG' => array( 'Congo (Congo-Brazzaville)', '242' ),
			'CM' => array( 'Cameroon', '237' ),
			'CF' => array( 'Central African Republic', '236' ),
			'TD' => array( 'Chad', '235' ),
			'GA' => array( 'Gabon', '241' ),
			'GQ' => array( 'Equatorial Guinea', '240' ),
			'ST' => array( 'São Tomé and Príncipe', '239' ),
			'AO' => array( 'Angola', '244' ),
			'ZM' => array( 'Zambia', '260' ),
			'MW' => array( 'Malawi', '265' ),
			'MZ' => array( 'Mozambique', '258' ),
			'ZW' => array( 'Zimbabwe', '263' ),
			'BW' => array( 'Botswana', '267' ),
			'NA' => array( 'Namibia', '264' ),
			'ZA' => array( 'South Africa', '27' ),
			'LS' => array( 'Lesotho', '266' ),
			'SZ' => array( 'Eswatini', '268' ),
			'MG' => array( 'Madagascar', '261' ),
			'MU' => array( 'Mauritius', '230' ),
			'SC' => array( 'Seychelles', '248' ),
			'KM' => array( 'Comoros', '269' ),
			'YT' => array( 'Mayotte', '262' ),
			'RE' => array( 'Réunion', '262' ),
			'NG' => array( 'Nigeria', '234' ),
			'GH' => array( 'Ghana', '233' ),
			'CI' => array( 'Côte d\'Ivoire', '225' ),
			'SN' => array( 'Senegal', '221' ),
			'ML' => array( 'Mali', '223' ),
			'BF' => array( 'Burkina Faso', '226' ),
			'NE' => array( 'Niger', '227' ),
			'TG' => array( 'Togo', '228' ),
			'BJ' => array( 'Benin', '229' ),
			'GW' => array( 'Guinea-Bissau', '245' ),
			'GN' => array( 'Guinea', '224' ),
			'SL' => array( 'Sierra Leone', '232' ),
			'LR' => array( 'Liberia', '231' ),
			'GM' => array( 'Gambia', '220' ),
			'MR' => array( 'Mauritania', '222' ),
			'CV' => array( 'Cape Verde', '238' ),
			'EH' => array( 'Western Sahara', '212' ),
			'BR' => array( 'Brazil', '55' ),
			'AR' => array( 'Argentina', '54' ),
			'CL' => array( 'Chile', '56' ),
			'CO' => array( 'Colombia', '57' ),
			'PE' => array( 'Peru', '51' ),
			'VE' => array( 'Venezuela', '58' ),
			'EC' => array( 'Ecuador', '593' ),
			'BO' => array( 'Bolivia', '591' ),
			'PY' => array( 'Paraguay', '595' ),
			'UY' => array( 'Uruguay', '598' ),
			'GY' => array( 'Guyana', '592' ),
			'SR' => array( 'Suriname', '597' ),
			'GF' => array( 'French Guiana', '594' ),
			'MX' => array( 'Mexico', '52' ),
			'GT' => array( 'Guatemala', '502' ),
			'BZ' => array( 'Belize', '501' ),
			'SV' => array( 'El Salvador', '503' ),
			'HN' => array( 'Honduras', '504' ),
			'NI' => array( 'Nicaragua', '505' ),
			'CR' => array( 'Costa Rica', '506' ),
			'PA' => array( 'Panama', '507' ),
			'CU' => array( 'Cuba', '53' ),
			'JM' => array( 'Jamaica', '1876' ),
			'HT' => array( 'Haiti', '509' ),
			'DO' => array( 'Dominican Republic', '1' ),
			'PR' => array( 'Puerto Rico', '1' ),
			'TT' => array( 'Trinidad and Tobago', '1868' ),
			'BB' => array( 'Barbados', '1246' ),
			'BS' => array( 'Bahamas', '1242' ),
			'GD' => array( 'Grenada', '1473' ),
			'LC' => array( 'Saint Lucia', '1758' ),
			'VC' => array( 'Saint Vincent and the Grenadines', '1784' ),
			'AG' => array( 'Antigua and Barbuda', '1268' ),
			'KN' => array( 'Saint Kitts and Nevis', '1869' ),
			'DM' => array( 'Dominica', '1767' ),
			'AI' => array( 'Anguilla', '1264' ),
			'VG' => array( 'British Virgin Islands', '1284' ),
			'VI' => array( 'U.S. Virgin Islands', '1340' ),
			'KY' => array( 'Cayman Islands', '1345' ),
			'TC' => array( 'Turks and Caicos Islands', '1649' ),
			'MS' => array( 'Montserrat', '1664' ),
			'BM' => array( 'Bermuda', '1441' ),
			'AW' => array( 'Aruba', '297' ),
			'CW' => array( 'Curaçao', '599' ),
			'SX' => array( 'Sint Maarten', '1721' ),
			'GP' => array( 'Guadeloupe', '590' ),
			'MQ' => array( 'Martinique', '596' ),
			'BL' => array( 'Saint Barthélemy', '590' ),
			'MF' => array( 'Saint Martin', '590' ),
			'PM' => array( 'Saint Pierre and Miquelon', '508' ),
			'FK' => array( 'Falkland Islands', '500' ),
			'GL' => array( 'Greenland', '299' ),
			'FO' => array( 'Faroe Islands', '298' ),
			'GI' => array( 'Gibraltar', '350' ),
			'GG' => array( 'Guernsey', '44' ),
			'JE' => array( 'Jersey', '44' ),
			'IM' => array( 'Isle of Man', '44' ),
			'SH' => array( 'Saint Helena', '290' ),
			'IO' => array( 'British Indian Ocean Territory', '246' ),
			'AS' => array( 'American Samoa', '1684' ),
			'GU' => array( 'Guam', '1671' ),
			'MP' => array( 'Northern Mariana Islands', '1670' ),
		);
	}

	/**
	 * Render a custom country picker: a trigger button showing only the flag + dial
	 * code, and a dropdown panel (with search) showing flag + full name + dial code.
	 * A hidden input carries the selected ISO code for form submission; JS reads the
	 * trigger's data-dial-code attribute to build the full E.164 number.
	 */
	public static function render_picker( string $id, string $name, string $default_iso = 'US' ): void {
		$countries = self::all();
		uasort( $countries, static fn( $a, $b ) => strcmp( $a[0], $b[0] ) );

		$default_iso = isset( $countries[ $default_iso ] ) ? $default_iso : 'US';
		$default     = $countries[ $default_iso ];
		?>
		<div class="smsentry-country-picker">
			<input type="hidden"
			       id="<?php echo esc_attr( $id ); ?>"
			       name="<?php echo esc_attr( $name ); ?>"
			       class="smsentry-country-value"
			       value="<?php echo esc_attr( $default_iso ); ?>" />

			<button type="button"
			        class="smsentry-country-trigger"
			        data-dial-code="<?php echo esc_attr( $default[1] ); ?>"
			        aria-haspopup="listbox"
			        aria-expanded="false">
				<span class="smsentry-country-trigger-flag"><?php echo esc_html( self::flag_emoji( $default_iso ) ); ?></span>
				<span class="smsentry-country-trigger-code">+<?php echo esc_html( $default[1] ); ?></span>
				<span class="smsentry-country-caret" aria-hidden="true"></span>
			</button>

			<div class="smsentry-country-dropdown" role="listbox" hidden>
				<input type="text" class="smsentry-country-search" placeholder="<?php esc_attr_e( 'Search countries…', 'smsentry' ); ?>" />
				<ul class="smsentry-country-list">
					<?php foreach ( $countries as $iso => $data ) : ?>
						<li role="option"
						    data-iso="<?php echo esc_attr( $iso ); ?>"
						    data-dial-code="<?php echo esc_attr( $data[1] ); ?>"
						    data-search="<?php echo esc_attr( strtolower( $data[0] ) ); ?>"
						    class="<?php echo $iso === $default_iso ? 'is-selected' : ''; ?>">
							<span class="smsentry-country-flag"><?php echo esc_html( self::flag_emoji( $iso ) ); ?></span>
							<span class="smsentry-country-name"><?php echo esc_html( $data[0] ); ?></span>
							<span class="smsentry-country-code">+<?php echo esc_html( $data[1] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="smsentry-country-list-empty" hidden><?php esc_html_e( 'No countries found.', 'smsentry' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Build a regional-indicator flag emoji from an ISO 3166-1 alpha-2 code
	 * using only core PHP (no mbstring/intl dependency).
	 */
	private static function flag_emoji( string $iso2 ): string {
		$flag = '';
		foreach ( str_split( strtoupper( $iso2 ) ) as $char ) {
			$flag .= html_entity_decode( '&#' . ( 127397 + ord( $char ) ) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		return $flag;
	}
}
