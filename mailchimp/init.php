<?php
/**
 * Mailchimp.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main Class.
 */
class NGL_Mailchimp extends NGL_Abstract_Integration {

	public $app		= 'mailchimp';
	public $api_key = null;
	public $api 	= null;

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Include needed files.
		include_once 'lib/api.php';
		include_once 'lib/batch.php';

		$this->get_api_key();

		add_filter( 'newsltterglue_mailchimp_html_content', array( $this, 'html_content' ), 10, 2 );
	}

	/**
	 * Get API Key.
	 */
	public function get_api_key() {
		$integrations = get_option( 'newsletterglue_integrations' );
		$integration  = isset( $integrations[ $this->app ] ) ? $integrations[ $this->app] : '';
		$this->api_key = isset( $integration[ 'api_key' ] ) ? $integration[ 'api_key' ] : '';
	}

	/**
	 * Add Integration.
	 */
	public function add_integration( $args = array() ) {

		$args 		= $this->get_connection_args( $args );

		$api_key 	= $args[ 'api_key' ];
		$api_url 	= $args[ 'api_url' ];

		$this->api = new NGL_Mailchimp_API( $api_key );

		$this->api->verify_ssl = false;

		$account = $this->api->get( '/' );

		$valid_account = ! empty( $account ) && isset( $account[ 'account_id' ] ) ? true : false;

		if ( ! $valid_account ) {

			$this->remove_integration();

			$result = array( 'response' => 'invalid' );

			delete_option( 'newsletterglue_mailchimp' );

		} else {

			if ( ! $this->already_integrated( $this->app, $api_key ) ) {
				$this->save_integration( $api_key, $account );
			}

			$result = array( 'response' => 'successful' );

			update_option( 'newsletterglue_mailchimp', $account );

		}

		return $result;
	}

	/**
	 * Save Integration.
	 */
	public function save_integration( $api_key = '', $account = '' ) {

		// Set these in memory.
		$this->api_key = $api_key;

		delete_option( 'newsletterglue_integrations' );

		$integrations = get_option( 'newsletterglue_integrations' );

		$integrations[ $this->app ] = array();
		$integrations[ $this->app ][ 'api_key' ] = $api_key;

		$name = isset( $account[ 'account_name' ] ) ? $account[ 'account_name' ] : newsletterglue_get_default_from_name();

		$integrations[ $this->app ][ 'connection_name' ] = sprintf( __( '%s – %s', 'newsletter-glue' ), $name, newsletterglue_get_name( $this->app ) );

		update_option( 'newsletterglue_integrations', $integrations );

		// Add default options.
		$globals = get_option( 'newsletterglue_options' );

		$options = array(
			'from_name' 	=> $name,
			'from_email'	=> isset( $account[ 'email' ] ) ? $account[ 'email' ] : '',
		);

		foreach( $options as $key => $value ) {
			$globals[ $this->app ][ $key ] = $value;
		}

		update_option( 'newsletterglue_options', $globals );

		update_option( 'newsletterglue_admin_name', $name );

		update_option( 'newsletterglue_admin_address', isset( $account[ 'contact' ] ) ? $account[ 'contact' ][ 'addr1' ] : '' );
	}

	/**
	 * Connect.
	 */
	public function connect() {

		$this->api = new NGL_Mailchimp_API( $this->api_key );

		$this->api->verify_ssl = false;

	}

	/**
	 * Get form defaults.
	 */
	public function get_form_defaults() {

		$this->api = new NGL_Mailchimp_API( $this->api_key );

		$this->api->verify_ssl = false;

		$defaults[ 'audiences' ] = $this->get_audiences();

		return $defaults;
	}

	/**
	 * Get default list ID.
	 */
	public function get_default_list_id() {
		$audiences = array();

		$this->api = new NGL_Mailchimp_API( $this->api_key );

		$this->api->verify_ssl = false;

		$data = $this->api->get( 'lists', array( 'count' => 1000 ) );

		if ( ! empty( $data[ 'lists' ] ) ) {
			foreach( $data[ 'lists' ] as $key => $array ) {
				return $array[ 'id' ];
			}
		}

		return '';
	}

	/**
	 * Get audiences.
	 */
	public function get_audiences() {
		$audiences = array();

		$data = $this->api->get( 'lists', array( 'count' => 1000 ) );

		if ( ! empty( $data[ 'lists' ] ) ) {
			foreach( $data[ 'lists' ] as $key => $array ) {
				$audiences[ $array[ 'id' ] ] = $array[ 'name' ];
			}
		}

		asort( $audiences );

		return $audiences;
	}

	/**
	 * Get segments.
	 */
	public function get_segments( $audience_id = '' ) {

		$segments = array( '_everyone' => __( 'Everyone in audience', 'newsletter-glue' ) );

		$data = $this->api->get( 'lists/' . $audience_id . '/segments', array( 'count' => 1000 ) );

		if ( isset( $data['segments' ] ) && ! empty( $data['segments'] ) ) {
			foreach( $data['segments'] as $key => $array ) {
				$segments[ $array['id'] ] = $array['name'];
			}
		}

		asort( $segments );

		return $segments;

	}

	/**
	 * Get segments HTML.
	 */
	public function get_segments_html( $audience_id = '' ) {
		?>
		<div class="ngl-metabox-flex ngl-metabox-segment">
			<div class="ngl-metabox-header">
				<label for="ngl_segment"><?php esc_html_e( 'Segment / tag', 'newsletter-glue' ); ?></label>
				<?php $this->input_verification_info(); ?>
			</div>
			<div class="ngl-field">
				<?php
					$segment = '_everyone';

					newsletterglue_select_field( array(
						'id' 			=> 'ngl_segment',
						'legacy'		=> true,
						'helper'		=> sprintf( __( 'A specific group of subscribers. %s', 'newsletter-glue' ), '<a href="https://admin.mailchimp.com/audience/" target="_blank" class="ngl-link-inline-svg">' . __( 'Create segment', 'newsletter-glue' ) . ' [externallink]</a>' ),
						'options'		=> $this->get_segments( $audience_id ),
						'default'		=> $segment,
						'class'			=> 'ngl-ajax',
					) );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Send newsletter.
	 */
	public function send_newsletter( $post_id = 0, $data = array(), $test = false ) {

		if ( defined( 'NGL_SEND_IN_PROGRESS' ) ) {
			return;
		}

		define( 'NGL_SEND_IN_PROGRESS', 'sending' );

		$post = get_post( $post_id );

		// If no data was provided. Get it from the post.
		if ( empty( $data ) ) {
			$data = get_post_meta( $post_id, '_newsletterglue', true );
		}

		$subject 		= isset( $data['subject'] ) ? ngl_safe_title( $data[ 'subject' ] ) : ngl_safe_title( $post->post_title );
		$from_name		= isset( $data['from_name'] ) ? $data['from_name'] : newsletterglue_get_default_from_name();
		$from_email		= isset( $data['from_email'] ) ? $data['from_email'] : $this->get_current_user_email();
		$audience		= isset( $data['audience'] ) ? $data['audience'] : $this->get_default_list_id();
		$segment		= isset( $data['segment'] ) && $data['segment'] && ( $data['segment'] != '_everyone' ) ? $data['segment'] : '';
		$schedule  	 	= isset( $data['schedule'] ) ? $data['schedule'] : 'immediately';

		$subject = apply_filters( 'newsletterglue_email_subject_line', $subject, $post, $data, $test, $this );

		if ( $test ) {
			if ( $this->is_invalid_email( $data[ 'test_email' ] ) ) {
				return $this->is_invalid_email( $data[ 'test_email' ] );
			}
		}

		// API request.
		$this->api = new NGL_Mailchimp_API( $this->api_key );
		$this->api->verify_ssl = false;

		// Empty content.
		if ( $test && isset( $post->post_status ) && $post->post_status === 'auto-draft' ) {

			$response['fail'] = $this->nothing_to_send();

			return $response;
		}

		// Verify domain.
		$domain_parts = explode( '@', $from_email );
		$domain = isset( $domain_parts[1] ) ? $domain_parts[1] : '';

		$result = $this->api->get( 'verified-domains/' . $domain );

		if ( isset( $result['status'] ) && $result['status'] === 404 ) {

			// Add unverified domain as campaign data.
			if ( ! $test ) {
				newsletterglue_add_campaign_data( $post_id, $subject, $this->prepare_message( $result ) );
			}

			$result = array(
				'fail'	=> __( 'Your <strong>From Email</strong> address isn&rsquo;t verified.', 'newsletter-glue' ) . '<br />' . '<a href="https://admin.mailchimp.com/account/domains/" target="_blank" class="ngl-link-inline-svg">' . __( 'Verify email now', 'newsletter-glue' ) . ' <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path></svg></a> <a href="https://docs.newsletterglue.com/article/7-unverified-email" target="_blank" class="ngl-link-inline-svg">' . __( 'Learn more', 'newsletter-glue' ) . ' <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path></svg></a>',
			);

			return $result;

		}

		// Settings.
		$settings = array(
			'title'			=> ! empty( ngl_safe_title( $post->post_title ) ) ? ngl_safe_title( $post->post_title ) : $subject,
			'subject_line' 	=> $subject,
			'reply_to' 		=> $from_email,
			'from_name' 	=> $from_name,
			'auto_footer'	=> false,
		);

		// Setup campaign array.
		$campaign_array = array(
			'type' 			=>	'regular',
			'recipients' 	=> array(
				'list_id' 	=> $audience,
			),
			'settings'		=> $settings
		);

		// Add segment.
		if ( $segment ) {
			$campaign_array['recipients']['segment_opts'] = array( 'saved_segment_id' => ( int ) $segment );
		}

		// Create a campaign.
		$result = $this->api->post( 'campaigns', $campaign_array );

		// Get campaign ID.
		$response 	= $this->api->getLastResponse();
		$output 	= json_decode( $response['body'] );

		if ( ! empty( $output->id ) ) {

			$campaign_id = $output->id;

			$htmlData = newsletterglue_generate_content( $post, $subject, $this->app );
			$htmlData = str_replace( '<!--%%', '', $htmlData );
			$htmlData = str_replace( '%%-->', '', $htmlData );

			// Manage campaign content
			$result = $this->api->put( 'campaigns/' . $campaign_id . '/content', [
				'html'	=> $htmlData,
			] );

			if ( $test ) {

				$response = array();

				$test_emails = array();
				$test_emails[] = $data['test_email'];

				$result = $this->api->post( 'campaigns/' . $campaign_id . '/actions/test', array(
					'test_emails'	=> $test_emails,
					'send_type'		=> 'html',
				) );

				// Process test email response.
				if ( isset( $result['status'] ) && $result['status'] == 400 ) {

					$response['fail'] = $this->get_test_limit_msg();

				} else {

					$response['success'] = $this->get_test_success_msg();

				}

				// Let's delete the campaign.
				$this->api->delete( 'campaigns/' . $campaign_id );

				return $response;

			} else {

				if ( $schedule === 'immediately' ) {

					$result = $this->api->post( 'campaigns/' . $campaign_id . '/actions/send' );

				}

				if ( $schedule === 'draft' ) {

					$result = array(
						'status' => 'draft'
					);

				}

				newsletterglue_add_campaign_data( $post_id, $subject, $this->prepare_message( $result ), $campaign_id );

				return $result;

			}

		} else {

			$errors = array();

			if ( $test ) {
				if ( isset( $output->status ) ) {
					if ( $output->status == 400 ) {
						if ( 'settings.subject_line' === $output->errors[0]->field ) {
							$errors[ 'fail' ]   = __( 'Whoops! The subject line is empty.<br />Fill it out to send.', 'newsletter-glue' );
						}
					}
				}
				return $errors;
			}

			if ( ! $test ) {
				newsletterglue_add_campaign_data( $post_id, $subject, $this->prepare_message( $result ) );
			}

			return $result;

		}

	}

	/**
	 * Check if the account is free.
	 */
	public function is_free_account() {
		$options = get_option( 'newsletterglue_mailchimp' );

		if ( isset( $options[ 'pricing_plan_type' ] ) ) {
			if ( $options[ 'pricing_plan_type' ] === 'forever_free' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Test failed.
	 */
	public function get_test_limit_msg() {

		if ( $this->is_free_account() ) {
			$test_count = 24;
		} else {
			$test_count = 200;
		}

		$message = __( 'Try testing again tomorrow?', 'newsletter-glue' );
		$message .= '<br />';
		$message .= sprintf( __( 'You&rsquo;ve sent too many test emails today. Mailchimp only allows %s test emails every 24 hours for your account.', 'newsletter-glue' ), $test_count );

		return $message;
	}

	/**
	 * Prepare result for plugin.
	 */
	public function prepare_message( $result ) {
		$output = array();

		if ( isset( $result['status'] ) ) {

			if ( $result['status'] == 400 ) {
				$output[ 'status' ] 	= 400;
				$output[ 'type' ] 		= 'error';
				$output[ 'message' ] 	= __( 'Missing subject', 'newsletter-glue' );
				$output[ 'help' ]       = '';
			}

			if ( $result['status'] == 404 ) {
				$output[ 'status' ] 	= 404;
				$output[ 'type' ] 		= 'error';
				$output[ 'message' ] 	= __( 'Unverified domain', 'newsletter-glue' );
				$output[ 'notice' ]		= sprintf( __( 'Your email newsletter was not sent, because your email address is not verified. %s Or %s', 'newsletter-glue' ), 
				'<a href="https://admin.mailchimp.com/account/domains/" target="_blank" class="ngl-link-inline-svg">' . __( 'Verify email now', 'newsletter-glue' ) . ' <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path></svg></a>', '<a href="https://docs.newsletterglue.com/article/7-unverified-email" target="_blank">' . __( 'learn more.', 'newsletter-glue' ) . '</a>' );
				$output[ 'help' ]       = 'https://docs.newsletterglue.com/article/7-unverified-email';
			}

			if ( $result['status'] == 'draft' ) {
				$output[ 'status' ]		= 200;
				$output[ 'type' ]		= 'neutral';
				$output[ 'message' ]    = __( 'Saved as draft', 'newsletter-glue' );
			}

		} else {

			if ( $result === true ) {
				$output[ 'status' ] 	= 200;
				$output[ 'type'   ] 	= 'success';
				$output[ 'message' ] 	= __( 'Sent', 'newsletter-glue' );
			}

		}

		return $output;
	}

	/**
	 * Verify email address.
	 */
	public function verify_email( $email = '' ) {

		if ( ! $email ) {
			$response = array( 'failed' => __( 'Please enter email', 'newsletter-glue' ) );
		} elseif ( ! is_email( $email ) ) {
			$response = array( 'failed'	=> __( 'Invalid email', 'newsletter-glue' ) );
		}

		if ( ! empty( $response ) ) {
			return $response;
		}

		$this->api = new NGL_Mailchimp_API( $this->api_key );
		$this->api->verify_ssl = false;

		// Verify domain.
		$parts  = explode( '@', $email );
		$domain = isset( $parts[1] ) ? $parts[1] : '';

		$result = $this->api->get( 'verified-domains/' . $domain );

		if ( isset( $result['verified'] ) && $result['verified'] == true ) {

			$response = array(
				'success'	=> '<strong>' . __( 'Verified', 'newsletter-glue' ) . '</strong>',
			);

		} else {

			$response = array(
				'failed'			=> __( 'Not verified', 'newsletter-glue' ),
				'failed_details'	=> '<a href="https://admin.mailchimp.com/account/domains/" target="_blank" class="ngl-link-inline-svg">' . __( 'Verify email now', 'newsletter-glue' ) . ' <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path></svg></a> <a href="https://docs.newsletterglue.com/article/7-unverified-email" target="_blank" class="ngl-link-inline-svg">' . __( 'Learn more', 'newsletter-glue' ) . ' <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path></svg></a>',
			);

		}

		return $response;
	}

	/**
	 * Add user to this ESP.
	 */
	public function add_user( $data ) {
		extract( $data );

		if ( empty( $email ) ) {
			return -1;
		}

		$fname = '';
		$lname = '';

		$this->api = new NGL_Mailchimp_API( $this->api_key );
		$this->api->verify_ssl = false;

		if ( isset( $name ) ) {
			$name_array = $array = explode( ' ', $name, 2 );
			$fname = $name_array[0];
			$lname = isset( $name_array[1] ) ? $name_array[1] : '';
		}

		$double_optin = isset( $double_optin ) && $double_optin == 'no' ? 'subscribed' : 'pending';

		$hash 		= $this->api::subscriberHash( $email );
		$batch		= $this->api->new_batch();
		
		if ( ! empty( $list_id ) ) {
			$batch->put( "op$list_id", "lists/$list_id/members/$hash", [
					'email_address' 	=> $email,
					'status'        	=> $double_optin,
					'status_if_new' 	=> $double_optin,
					'merge_fields' 	 	=> array(
						'FNAME'	=> $fname,
						'LNAME'	=> $lname
					),
			] );
		}

		if ( isset( $extra_list ) && ! empty( $extra_list_id ) ) {
			$batch->put( "op$extra_list_id", "lists/$extra_list_id/members/$hash", [
					'email_address' 	=> $email,
					'status'        	=> $double_optin,
					'status_if_new' 	=> $double_optin,
					'merge_fields' 	 	=> array(
						'FNAME'	=> $fname,
						'LNAME'	=> $lname
					),
			] );
		}

		$batch->execute();

		$result = $batch->check_status();

		return true;

	}

	/**
	 * Get connect settings.
	 */
	public function get_connect_settings( $integrations = array() ) {

		$app = $this->app;

		newsletterglue_text_field( array(
			'id' 			=> "ngl_{$app}_key",
			'placeholder' 	=> esc_html__( 'Enter API Key', 'newsletter-glue' ),
			'value'			=> isset( $integrations[ $app ]['api_key'] ) ? $integrations[ $app ]['api_key'] : '',
			'helper'		=> '<a href="https://admin.mailchimp.com/account/api-key-popup/" target="_blank" class="ngl-link-inline-svg">' . __( 'Get API key', 'newsletter-glue' ) . ' [externallink]</a>',
			'type'			=> 'password',
		) );

	}

	/**
	 * Replace universal tags with esp tags.
	 */
	public function html_content( $html, $post_id ) {

		$html = $this->convert_tags( $html, $post_id );

		$html = $this->convert_conditions( $html );

		return $html;
	}

	/**
	 * Code supported tags for this ESP.
	 */
	public function get_tag( $tag, $post_id = 0, $fallback = null ) {

		switch ( $tag ) {
			case 'unsubscribe_link' :
				return '*|UNSUB|*';
			break;
			case 'admin_address' :
				return '*|USER:ADDRESS|*';
			break;
			case 'admin_address_html' :
				return '*|HTML:USER_ADDRESS_HTML|*';
			break;
			case 'rewards' :
				return '*|IF:REWARDS|* *|REWARDS|* *|END:IF|*';
			break;
			case 'list' :
				return '*|LIST:NAME|*';
			break;
			case 'first_name' :
				return '*|FNAME|*';
			break;
			case 'last_name' :
				return '*|LNAME|*';
			break;
			case 'email' :
				return '*|EMAIL|*';
			break;
			case 'address' :
				return '*|ADDRESS|*';
			break;
			case 'update_preferences' :
				return '*|UPDATE_PROFILE|*';
			break;
			default :
				return apply_filters( "newsletterglue_{$this->app}_custom_tag", '', $tag, $post_id );
			break;
		}

		return false;
	}

	/**
	 * Get lists compat.
	 */
	public function _get_lists_compat() {
		$this->api = new NGL_Mailchimp_API( $this->api_key );
		$this->api->verify_ssl = false;
		return $this->get_audiences();
	}

	/**
	 * Configure options array for this ESP.
	 */
	public function option_array() {
		return array(
			'audience' 	=> array(
				'type'		=> 'select',
				'callback'	=> 'get_audiences',
				'onchange'  => 'segment',
				'title'     => __( 'Audience', 'newsletter-glue' ),
				'help'		=> __( 'Who receives your email.', 'newsletter-glue' ),
			),
			'segment'	=> array(
				'type'		=> 'select',
				'callback' 	=> 'get_segments',
				'param'		=> 'audience',
				'title'		=> __( 'Segment / tag', 'newsletter-glue' ),
				'help'		=> sprintf( __( 'A specific group of subscribers. %s', 'newsletter-glue' ), '<a href="https://admin.mailchimp.com/audience/">' . __( 'Create segment', 'newsletter-glue' ) . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="components-external-link__icon css-6wogo1-StyledIcon etxm6pv0" role="img" aria-hidden="true" focusable="false"><path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path></svg></a>' ),
				'placeholder' => __( 'Select a segment...', 'newsletter-glue' ),
			)
		);
	}

	/**
	 * Get custom fields of esp
	 */
	public function get_custom_fields() {
		$_fields = array();

		$this->api = new NGL_Mailchimp_API( $this->api_key );
		$this->api->verify_ssl = false;

		$audiences = $this->get_audiences();
		if( is_array( $audiences ) ) {
			foreach( $audiences as $audience_id => $audience_name ) {
				$response = $this->api->get( 'lists/' . $audience_id . '/merge-fields', array( 'count' => 1000 ) );
				if ( ! empty( $response ) && isset( $response[ 'merge_fields' ] ) ) {
					foreach( $response[ 'merge_fields' ] as $key => $array ) {
						if( isset( $array[ 'name' ] ) && ! empty( $array[ 'name' ] ) ) {
							$_fields[] = array( 'label' => $array[ 'name' ], 'value' => $array[ 'tag' ] );
						}
					}
				}
			}
		}

		if( count( $_fields ) ) {
			array_multisort( array_column( $_fields, 'label' ), SORT_ASC, $_fields );
			array_unshift( $_fields, array( 'value' => '', 'label' => 'Select an option' ) );
		}

		return $_fields;
	}

	/**
	 * Convert conditional statements of esp
	 */
	public function convert_conditions( $html ) {
		$output = new simple_html_dom();
		$output->load( $html, true, false );

		$replace = '[data-conditions]';
		foreach( $output->find( $replace ) as $key => $element ) {

			$conditions = json_decode( $element->{ 'data-conditions' } );
			$element->removeAttribute( 'data-conditions' );

			$contentStart = '';
			$contentEnd   = '';

			foreach( $conditions as $condition ) {
				$key          = $condition->key;
				$operator     = $condition->operator;
				$value        = $condition->value;

				if( $operator == "ex" ) {

					$contentStart .= "*|IF:$key|*";
	
				} else if( $operator == "nex" ) {
					
					$contentStart .= "*|IFNOT:$key|*";
	
				} else if( $operator == "eq" ) {
	
					$contentStart .= "*|IF:$key=$value|*";
	
				} else if( $operator == "neq" ) {

					$contentStart .= "*|IF:$key!=$value|*";

				} else if( $operator == "gt" ) {

					$contentStart .= "*|IF:$key > $value|*";

				} else if( $operator == "lt" ) {

					$contentStart .= "*|IF:$key < $value|*";

				} else if( $operator == "gte" ) {

					$contentStart .= "*|IF:$key >= $value|*";

				} else if( $operator == "lte" ) {

					$contentStart .= "*|IF:$key <= $value|*";

				}

				$contentStart .= " ";
				$contentEnd = " *|END:IF|*$contentEnd";
			}

			if( ! empty( $contentStart ) && ! empty( $contentEnd ) ) {
				$content  = "<!--%%" . trim( $contentStart ) . "%%-->";
				$content .= $element->outertext;
				$content .= "<!--%%" . trim( $contentEnd ) . "%%-->";
				$element->outertext = $content;
			}

		}

		$output->save();

		return ( string ) $output;
	}

}