<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Calculates chronological swimming age groups from a swimmer's private date of birth. */
final class Age_Group {
	public static function for_user( $user_id, $on_date = null ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return null;
		}
		$birthdate = (string) get_user_meta( $user_id, 'swimlog_birthdate', true );
		if ( '' === $birthdate ) {
			return null;
		}
		return self::from_birthdate( $birthdate, $on_date );
	}

	public static function from_birthdate( $birthdate, $on_date = null ) {
		$timezone = wp_timezone();
		$birth = \DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $birthdate, $timezone );
		$birth_errors = \DateTimeImmutable::getLastErrors();
		if ( ! $birth || ( false !== $birth_errors && ( $birth_errors['warning_count'] || $birth_errors['error_count'] ) ) || $birth->format( 'Y-m-d' ) !== (string) $birthdate ) {
			return null;
		}

		if ( null === $on_date || '' === $on_date ) {
			$when = new \DateTimeImmutable( 'today', $timezone );
		} elseif ( $on_date instanceof \DateTimeInterface ) {
			$when = new \DateTimeImmutable( $on_date->format( 'Y-m-d' ), $timezone );
		} else {
			$when = \DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $on_date, $timezone );
			$when_errors = \DateTimeImmutable::getLastErrors();
			if ( ! $when || ( false !== $when_errors && ( $when_errors['warning_count'] || $when_errors['error_count'] ) ) || $when->format( 'Y-m-d' ) !== (string) $on_date ) {
				return null;
			}
		}

		if ( $when < $birth ) {
			return null;
		}

		$age = (int) $birth->diff( $when )->y;
		return array(
			'age'   => $age,
			'label' => self::label_for_age( $age ),
		);
	}

	public static function label_for_age( $age ) {
		$age = (int) $age;
		if ( $age <= 10 ) return __( '10 & Under', 'chriss-swim-training-progress-evaluation' );
		if ( $age <= 12 ) return __( '11–12', 'chriss-swim-training-progress-evaluation' );
		if ( $age <= 14 ) return __( '13–14', 'chriss-swim-training-progress-evaluation' );
		if ( $age <= 16 ) return __( '15–16', 'chriss-swim-training-progress-evaluation' );
		if ( $age <= 18 ) return __( '17–18', 'chriss-swim-training-progress-evaluation' );
		if ( $age <= 24 ) return __( '19–24', 'chriss-swim-training-progress-evaluation' );

		$lower = 25 + ( 5 * intdiv( $age - 25, 5 ) );
		return sprintf(
			/* translators: 1: lower age in a five-year swimming age group, 2: upper age. */
			__( '%1$d–%2$d', 'chriss-swim-training-progress-evaluation' ),
			$lower,
			$lower + 4
		);
	}
}
