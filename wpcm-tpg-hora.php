<?php
/**
 * Plugin Name: WPCM TPG Hora
 * Plugin URI: https://rd5.com.br/dev/
 * Description: Exibe a data atual no fuso horário escolhido no painel, com opções adicionais de email, telefone e local.
 * Version: 1.7.0
 * Author: Daniel Oliveira da Paixão
 * Author URI: https://rd5.com.br/dev/
 * License: GPL2
 * Text Domain: wpcm-tpg-hora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPCM_TPG_Hora {

	/**
	 * Singleton instance.
	 *
	 * @var WPCM_TPG_Hora|null
	 */
	private static $instance = null;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_slug = 'wpcm-tpg-hora';

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private $option_name = 'wpcm_tpg_hora_options';

	/**
	 * Default settings.
	 *
	 * @var array<string,string>
	 */
	private $defaults = array(
		'timezone'   => 'America/Porto_Velho',
		'font_color' => '#333333',
		'email'      => '',
		'telefone'   => '',
		'local'      => '',
	);

	/**
	 * Cached options.
	 *
	 * @var array<string,string>|null
	 */
	private $cached_options = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		add_shortcode( 'data_formatada', array( $this, 'shortcode_data_formatada' ) );
		add_shortcode( 'wpcm_tpg_hora', array( $this, 'shortcode_data_formatada' ) );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return WPCM_TPG_Hora
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			$this->plugin_slug,
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}

	/**
	 * Return defaults.
	 *
	 * @return array<string,string>
	 */
	private function get_defaults() {
		return $this->defaults;
	}

	/**
	 * Get merged options.
	 *
	 * @param bool $force_refresh Force reload from DB.
	 * @return array<string,string>
	 */
	private function get_options( $force_refresh = false ) {
		if ( true === $force_refresh || null === $this->cached_options ) {
			$saved = get_option( $this->option_name, array() );
			$saved = is_array( $saved ) ? $saved : array();

			$this->cached_options = wp_parse_args( $saved, $this->get_defaults() );
		}

		return $this->cached_options;
	}

	/**
	 * Safely log only in debug environments.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	private function maybe_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[WPCM TPG Hora] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'wpcm_tpg_hora_options_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => $this->get_defaults(),
			)
		);

		add_settings_section(
			'wpcm_tpg_hora_section',
			__( 'Configurações de Exibição', 'wpcm-tpg-hora' ),
			array( $this, 'settings_section_callback' ),
			'wpcm-tpg-hora'
		);

		add_settings_field(
			'font_color',
			__( 'Cor da Fonte', 'wpcm-tpg-hora' ),
			array( $this, 'font_color_callback' ),
			'wpcm-tpg-hora',
			'wpcm_tpg_hora_section'
		);

		add_settings_field(
			'timezone',
			__( 'Fuso Horário', 'wpcm-tpg-hora' ),
			array( $this, 'timezone_callback' ),
			'wpcm-tpg-hora',
			'wpcm_tpg_hora_section'
		);

		add_settings_field(
			'email',
			__( 'Email', 'wpcm-tpg-hora' ),
			array( $this, 'email_callback' ),
			'wpcm-tpg-hora',
			'wpcm_tpg_hora_section'
		);

		add_settings_field(
			'telefone',
			__( 'Telefone', 'wpcm-tpg-hora' ),
			array( $this, 'telefone_callback' ),
			'wpcm-tpg-hora',
			'wpcm_tpg_hora_section'
		);

		add_settings_field(
			'local',
			__( 'Local', 'wpcm-tpg-hora' ),
			array( $this, 'local_callback' ),
			'wpcm-tpg-hora',
			'wpcm_tpg_hora_section'
		);
	}

	/**
	 * Section description.
	 *
	 * @return void
	 */
	public function settings_section_callback() {
		echo '<p>' . esc_html__( 'Escolha o fuso horário usado pelo shortcode e personalize os dados complementares exibidos.', 'wpcm-tpg-hora' ) . '</p>';
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Input data.
	 * @return array<string,string>
	 */
	public function sanitize_options( $input ) {
		$input = is_array( $input ) ? $input : array();

		$sanitized = array();
		$defaults  = $this->get_defaults();

		$font_color = isset( $input['font_color'] ) ? sanitize_hex_color( $input['font_color'] ) : '';
		$sanitized['font_color'] = $font_color ? $font_color : $defaults['font_color'];

		$timezone = isset( $input['timezone'] ) ? sanitize_text_field( wp_unslash( $input['timezone'] ) ) : $defaults['timezone'];
		$sanitized['timezone'] = in_array( $timezone, timezone_identifiers_list(), true ) ? $timezone : $defaults['timezone'];

		$sanitized['email'] = isset( $input['email'] ) ? sanitize_email( wp_unslash( $input['email'] ) ) : '';

		$telefone = isset( $input['telefone'] ) ? wp_unslash( $input['telefone'] ) : '';
		$telefone = preg_replace( '/[^0-9+() \-]/', '', $telefone );
		$sanitized['telefone'] = sanitize_text_field( (string) $telefone );

		$sanitized['local'] = isset( $input['local'] ) ? sanitize_text_field( wp_unslash( $input['local'] ) ) : '';

		$this->cached_options = null;

		return wp_parse_args( $sanitized, $defaults );
	}

	/**
	 * Build validated render args.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return array<string,string>
	 */
	private function build_render_args( $atts = array() ) {
		$options = $this->get_options();

		$atts = shortcode_atts(
			array(
				'timezone'   => $options['timezone'],
				'font_color' => $options['font_color'],
				'email'      => $options['email'],
				'telefone'   => $options['telefone'],
				'local'      => $options['local'],
			),
			(array) $atts,
			'data_formatada'
		);

		$timezone = sanitize_text_field( (string) $atts['timezone'] );
		if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
			$timezone = $this->get_defaults()['timezone'];
		}

		$font_color = sanitize_hex_color( (string) $atts['font_color'] );
		if ( ! $font_color ) {
			$font_color = $this->get_defaults()['font_color'];
		}

		return array(
			'timezone'   => $timezone,
			'font_color' => $font_color,
			'email'      => sanitize_email( (string) $atts['email'] ),
			'telefone'   => sanitize_text_field( preg_replace( '/[^0-9+() \-]/', '', (string) $atts['telefone'] ) ),
			'local'      => sanitize_text_field( (string) $atts['local'] ),
		);
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_data_formatada( $atts = array() ) {
		$args = $this->build_render_args( $atts );

		try {
			$timezone = new DateTimeZone( $args['timezone'] );
		} catch ( Exception $e ) {
			$this->maybe_log( 'Fuso horário inválido recebido na renderização: ' . $args['timezone'] );
			$timezone = new DateTimeZone( $this->get_defaults()['timezone'] );
		}

		try {
			$datetime = new DateTimeImmutable( 'now', $timezone );
		} catch ( Exception $e ) {
			$this->maybe_log( 'Falha ao criar DateTimeImmutable.' );
			return '';
		}

		$meses = array(
			1  => __( 'janeiro', 'wpcm-tpg-hora' ),
			2  => __( 'fevereiro', 'wpcm-tpg-hora' ),
			3  => __( 'março', 'wpcm-tpg-hora' ),
			4  => __( 'abril', 'wpcm-tpg-hora' ),
			5  => __( 'maio', 'wpcm-tpg-hora' ),
			6  => __( 'junho', 'wpcm-tpg-hora' ),
			7  => __( 'julho', 'wpcm-tpg-hora' ),
			8  => __( 'agosto', 'wpcm-tpg-hora' ),
			9  => __( 'setembro', 'wpcm-tpg-hora' ),
			10 => __( 'outubro', 'wpcm-tpg-hora' ),
			11 => __( 'novembro', 'wpcm-tpg-hora' ),
			12 => __( 'dezembro', 'wpcm-tpg-hora' ),
		);

		$dia = $datetime->format( 'd' );
		$mes = (int) $datetime->format( 'n' );
		$ano = $datetime->format( 'Y' );

		$output  = '<span class="wpcm-tpg-hora-texto" style="color:' . esc_attr( $args['font_color'] ) . ';">';
		$output .= esc_html( $dia . ' de ' . $meses[ $mes ] . ' de ' . $ano );
		$output .= '</span>';

		$output .= $this->get_additional_info_item( __( 'Email', 'wpcm-tpg-hora' ), $args['email'], $args['font_color'] );
		$output .= $this->get_additional_info_item( __( 'Telefone', 'wpcm-tpg-hora' ), $args['telefone'], $args['font_color'] );
		$output .= $this->get_additional_info_item( __( 'Local', 'wpcm-tpg-hora' ), $args['local'], $args['font_color'] );

		return $output;
	}

	/**
	 * Render additional info item.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $font_color Font color.
	 * @return string
	 */
	private function get_additional_info_item( $label, $value, $font_color ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		return ' | <span style="color:' . esc_attr( $font_color ) . ';">' .
			esc_html( $label . ': ' . $value ) .
		'</span>';
	}

	/**
	 * Font color field.
	 *
	 * @return void
	 */
	public function font_color_callback() {
		$options = $this->get_options();

		echo '<input type="text" class="wpcm-tpg-hora-color-field" name="' . esc_attr( $this->option_name ) . '[font_color]" value="' . esc_attr( $options['font_color'] ) . '" data-default-color="#333333" />';
	}

	/**
	 * Timezone field.
	 *
	 * @return void
	 */
	public function timezone_callback() {
		$options = $this->get_options();

		$favorite_timezones = array(
			'America/Porto_Velho' => 'America/Porto_Velho',
			'America/Sao_Paulo'   => 'America/Sao_Paulo',
			'America/Santiago'    => 'America/Santiago',
			'Pacific/Easter'      => 'Pacific/Easter',
			'America/Mexico_City' => 'America/Mexico_City',
			'UTC'                 => 'UTC',
		);

		echo '<select name="' . esc_attr( $this->option_name ) . '[timezone]" style="min-width:320px;">';

		echo '<optgroup label="' . esc_attr__( 'Sugestões', 'wpcm-tpg-hora' ) . '">';
		foreach ( $favorite_timezones as $tz ) {
			echo '<option value="' . esc_attr( $tz ) . '" ' . selected( $options['timezone'], $tz, false ) . '>' . esc_html( $tz ) . '</option>';
		}
		echo '</optgroup>';

		echo '<optgroup label="' . esc_attr__( 'Todos os fusos', 'wpcm-tpg-hora' ) . '">';
		foreach ( timezone_identifiers_list() as $tz ) {
			echo '<option value="' . esc_attr( $tz ) . '" ' . selected( $options['timezone'], $tz, false ) . '>' . esc_html( $tz ) . '</option>';
		}
		echo '</optgroup>';

		echo '</select>';

		echo '<p class="description">' . esc_html__( 'Exemplo: America/Santiago, Pacific/Easter, America/Porto_Velho, UTC.', 'wpcm-tpg-hora' ) . '</p>';
	}

	/**
	 * Email field.
	 *
	 * @return void
	 */
	public function email_callback() {
		$options = $this->get_options();

		echo '<input type="email" name="' . esc_attr( $this->option_name ) . '[email]" value="' . esc_attr( $options['email'] ) . '" class="regular-text" />';
	}

	/**
	 * Telefone field.
	 *
	 * @return void
	 */
	public function telefone_callback() {
		$options = $this->get_options();

		echo '<input type="text" name="' . esc_attr( $this->option_name ) . '[telefone]" value="' . esc_attr( $options['telefone'] ) . '" class="regular-text" placeholder="(69) 99999-9999" />';
		echo '<p class="description">' . esc_html__( 'Formato recomendado: (99) 99999-9999', 'wpcm-tpg-hora' ) . '</p>';
	}

	/**
	 * Local field.
	 *
	 * @return void
	 */
	public function local_callback() {
		$options = $this->get_options();

		echo '<input type="text" name="' . esc_attr( $this->option_name ) . '[local]" value="' . esc_attr( $options['local'] ) . '" class="regular-text" />';
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Configurações WPCM TPG Hora', 'wpcm-tpg-hora' ),
			__( 'WPCM TPG Hora', 'wpcm-tpg-hora' ),
			'manage_options',
			'wpcm-tpg-hora',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	public function settings_page() {
		$options = $this->get_options( true );
		?>
		<div class="wrap wpcm-tpg-hora-admin">
			<h1><?php esc_html_e( 'Configurações WPCM TPG Hora', 'wpcm-tpg-hora' ); ?></h1>

			<div class="wpcm-tpg-hora-preview">
				<h2><?php esc_html_e( 'Visualização', 'wpcm-tpg-hora' ); ?></h2>
				<div class="preview-box">
					<?php echo wp_kses_post( $this->shortcode_data_formatada() ); ?>
				</div>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							__( 'Fuso atualmente salvo: %s', 'wpcm-tpg-hora' ),
							$options['timezone']
						)
					);
					?>
				</p>
				<p class="description"><?php esc_html_e( 'Use o shortcode [data_formatada] para exibir a data em qualquer lugar.', 'wpcm-tpg-hora' ); ?></p>
				<p class="description"><?php esc_html_e( 'Também é possível sobrescrever no shortcode: [data_formatada timezone="America/Santiago"]', 'wpcm-tpg-hora' ); ?></p>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'wpcm_tpg_hora_options_group' );
				do_settings_sections( 'wpcm-tpg-hora' );
				submit_button( __( 'Salvar configurações', 'wpcm-tpg-hora' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_wpcm-tpg-hora' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_register_style( 'wpcm-tpg-hora-admin-inline', false, array( 'wp-color-picker' ), '1.7.0' );
		wp_enqueue_style( 'wpcm-tpg-hora-admin-inline' );

		wp_add_inline_style(
			'wpcm-tpg-hora-admin-inline',
			'
			.wpcm-tpg-hora-admin .wpcm-tpg-hora-preview{
				background:#fff;
				border:1px solid #ccd0d4;
				padding:16px 18px;
				margin:16px 0 20px;
				border-radius:6px;
			}
			.wpcm-tpg-hora-admin .preview-box{
				background:#f6f7f7;
				padding:14px 16px;
				border:1px solid #dcdcde;
				border-radius:4px;
				margin-bottom:10px;
				font-size:16px;
				line-height:1.6;
			}
			.wpcm-tpg-hora-admin select{
				max-width:100%;
			}
			'
		);

		$inline_js = <<<JS
jQuery(document).ready(function($){
	$('.wpcm-tpg-hora-color-field').wpColorPicker();
});
JS;

		wp_add_inline_script( 'wp-color-picker', $inline_js );
	}
}

/**
 * Bootstrap.
 *
 * @return WPCM_TPG_Hora
 */
function wpcm_tpg_hora_init() {
	return WPCM_TPG_Hora::get_instance();
}

wpcm_tpg_hora_init();
