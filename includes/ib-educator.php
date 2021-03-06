<?php

class IB_Educator {
	private static $instance = null;
	private $payments;
	private $entries;
	private $questions;
	private $choices;
	private $answers;
	private $grades;

	/**
	 * Constructor.
	 *
	 * @access private
	 */
	private function __construct() {
		$tables = ib_edu_table_names();
		$this->payments  = $tables['payments'];
		$this->entries   = $tables['entries'];
		$this->questions = $tables['questions'];
		$this->choices   = $tables['choices'];
		$this->answers   = $tables['answers'];
		$this->grades    = $tables['grades'];
	}

	/**
	 * Get instance.
	 *
	 * @return IB_Educator
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get course access status.
	 *
	 * @param int $course_id
	 * @param int $user_id
	 * @return string
	 */
	public function get_access_status( $course_id, $user_id ) {
		global $wpdb;
		$status = '';
		$sql = "SELECT ee.course_id, ee.user_id, ep.payment_status, ee.entry_status FROM $this->entries ee
			LEFT JOIN $this->payments ep ON ep.ID=ee.payment_id
			WHERE ee.course_id=%d AND ee.user_id=%d";
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $course_id, $user_id ) );
		$has_complete = false;
		$has_cancelled = false;
		
		if ( $results ) {
			foreach ( $results as $result ) {
				if ( 'complete' == $result->entry_status ) {
					$has_complete = true;
				} elseif ( 'cancelled' == $result->entry_status ) {
					$has_cancelled = true;
				} else {
					// Found payment/entry record that is neither complete, nor cancelled.
					if ( 'pending' == $result->entry_status ) {
						$status = 'pending_entry';
					} elseif ( 'inprogress' == $result->entry_status ) {
						$status = 'inprogress';
					} elseif ( 'pending' == $result->payment_status ) {
						$status = 'pending_payment';
					}
				}
			}
		}

		if ( empty( $status ) ) {
			$status = ( $has_complete ) ? 'course_complete' : 'forbidden';
		}

		return apply_filters( 'ib_educator_access_status', $status, $course_id, $user_id );
	}

	/**
	 * Determine if a user can pay for a given course.
	 *
	 * @deprecated 1.3.0
	 * @param int $course_id
	 * @param int $user_id
	 * @return boolean
	 */
	public function user_can_pay( $course_id, $user_id ) {
		return in_array( $this->get_access_status( $course_id, $user_id ), array( 'forbidden', 'course_complete' ) );
	}

	/**
	 * Save payment to database.
	 *
	 * @param array $data
	 * @return IB_Educator_Payment
	 */
	public function add_payment( $data ) {
		$payment = edr_get_payment();

		if ( ! empty( $data['course_id'] ) ) {
			$payment->course_id = $data['course_id'];
		}
		
		$payment->user_id = $data['user_id'];
		
		if ( ! empty( $data['object_id'] ) ) {
			$payment->object_id = $data['object_id'];
		}
		
		$payment->payment_type = $data['payment_type'];
		$payment->payment_gateway = $data['payment_gateway'];
		$payment->payment_status = $data['payment_status'];
		$payment->amount = $data['amount'];
		$payment->currency = $data['currency'];

		if ( ! empty( $data['tax'] ) ) {
			$payment->tax = $data['tax'];
		}
		
		$payment->save();

		return $payment;
	}

	/**
	 * Get entry from database.
	 *
	 * @param array $args
	 * @return false|IB_Educator_Entry
	 */
	public function get_entry( $args ) {
		global $wpdb;
		$sql = "SELECT * FROM {$this->entries} WHERE 1";

		// Filter by payment_id.
		if ( isset( $args['payment_id'] ) ) {
			$sql .= ' AND payment_id=' . absint( $args['payment_id'] );
		}

		// Filter by course_id.
		if ( isset( $args['course_id'] ) ) {
			$sql .= ' AND course_id=' . absint( $args['course_id'] );
		}

		// Filter by user_id.
		if ( isset( $args['user_id'] ) ) {
			$sql .= ' AND user_id=' . absint( $args['user_id'] );
		}

		// Filter by entry_status.
		if ( isset( $args['entry_status'] ) ) {
			$sql .= $wpdb->prepare( ' AND entry_status = %s', $args['entry_status'] );
		}

		$row = $wpdb->get_row( $sql );

		if ( $row ) {
			return edr_get_entry( $row );
		}

		return false;
	}

	/**
	 * Get entries.
	 *
	 * @param array $args
	 * @return array
	 */
	public function get_entries( $args, $output_type = 'OBJECT' ) {
		global $wpdb;

		$sql = "SELECT * FROM $this->entries WHERE 1";

		// Entry ID.
		if ( isset( $args['entry_id'] ) ) {
			if ( is_array( $args['entry_id'] ) ) {
				$ids = implode( ',', array_map( 'intval', $args['entry_id'] ) );
				$sql .= " AND ID IN ($ids)";
			} else {
				$sql .= ' AND ID = ' . intval( $args['entry_id'] );
			}
		}

		// Course ID.
		if ( isset( $args['course_id'] ) ) {
			if ( is_array( $args['course_id'] ) ) {
				$ids = implode( ',', array_map( 'intval', $args['course_id'] ) );
				$sql .= " AND course_id IN ($ids)";
			} else {
				$sql .= ' AND course_id = ' . intval( $args['course_id'] );
			}
		}

		// User ID.
		if ( isset( $args['user_id'] ) ) {
			if ( is_array( $args['user_id'] ) ) {
				$ids = implode( ',', array_map( 'intval', $args['user_id'] ) );
				$sql .= " AND user_id IN ($ids)";
			} else {
				$sql .= ' AND user_id = ' . intval( $args['user_id'] );
			}
		}

		// Payment ID.
		if ( isset( $args['payment_id'] ) ) {
			$sql .= ' AND payment_id = ' . intval( $args['payment_id'] );
		}

		// Entry status.
		if ( isset( $args['entry_status'] ) ) {
			$sql .= $wpdb->prepare( ' AND entry_status = %s', $args['entry_status'] );
		}

		// Entry origin.
		if ( isset( $args['entry_origin'] ) ) {
			$sql .= $wpdb->prepare( ' AND entry_origin = %s', $args['entry_origin'] );
		}

		// With or without pagination?
		$has_pagination = isset( $args['page'] ) && isset( $args['per_page'] ) && is_numeric( $args['page'] ) && is_numeric( $args['per_page'] );
		$pagination_sql = '';

		if ( $has_pagination ) {
			$num_rows = $wpdb->get_var( str_replace( 'SELECT *', 'SELECT count(1)', $sql ) );
			$pagination_sql .= ' LIMIT ' . ( ( $args['page'] - 1 ) * $args['per_page'] ) . ', ' . $args['per_page'];
		}

		$entries = $wpdb->get_results( $sql . ' ORDER BY entry_date DESC' . $pagination_sql, $output_type );

		if ( $entries ) {
			if ( 'OBJECT' == $output_type ) {
				$entries = array_map( 'edr_get_entry', $entries );
			}
		}

		if ( $has_pagination ) {
			return array(
				'num_pages' => ceil( $num_rows / $args['per_page'] ),
				'num_items' => $num_rows,
				'rows'      => $entries,
			);
		}

		return $entries;
	}

	/**
	 * Get entries count grouped by entry status.
	 *
	 * @deprecated 1.3.0
	 * @param array $args
	 * @return array
	 */
	public function get_entries_count( $args = array() ) {
		global $wpdb;

		$sql = "SELECT entry_status, count(1) as num_rows FROM {$this->entries} WHERE 1";

		// Filter by course_id.
		if ( isset( $args['course_id'] ) ) {
			$course_id = array();

			if ( is_array( $args['course_id'] ) ) {
				foreach ( $args['course_id'] as $id ) {
					$course_id[] = absint( $id );
				}
			} else {
				$course_id[] = absint( $args['course_id'] );
			}

			if ( ! empty( $course_id ) ) {
				$sql .= ' AND course_id IN (' . implode( ',', $course_id ) . ')';
			}
		}

		$sql .= ' GROUP BY entry_status';

		return $wpdb->get_results( $sql, OBJECT_K );
	}

	/**
	 * Get the student's courses.
	 *
	 * @param int $user_id
	 * @return array
	 */
	public function get_student_courses( $user_id ) {
		global $wpdb;
		
		if ( absint( $user_id ) != $user_id ) {
			return false;
		}
		
		$ids = array();

		$entries = $this->get_entries( array( 'user_id'  => $user_id ) );
		
		if ( ! empty( $entries ) ) {
			$statuses = array();

			foreach ( $entries as $row ) {
				$ids[] = $row->course_id;

				if ( isset( $statuses[ $row->entry_status ] ) ) {
					++$statuses[ $row->entry_status ];
				} else {
					$statuses[ $row->entry_status ] = 0;
				}
			}

			$query = new WP_Query( array(
				'post_type'      => 'ib_educator_course',
				'post_status'    => 'publish',
				'post__in'       => $ids,
				'posts_per_page' => -1,
				'orderby'        => 'post__in',
				'order'          => 'ASC',
			) );

			if ( $query->have_posts() ) {
				$posts = array();

				foreach ( $query->posts as $post ) {
					$posts[ $post->ID ] = $post;
				}

				return array(
					'entries'  => $entries,
					'courses'  => $posts,
					'statuses' => $statuses
				);
			}
		}

		return false;
	}

	/**
	 * Get courses that are pending payment.
	 *
	 * @param int $user_id
	 * @return false|array of WP_Post objects
	 */
	public function get_pending_courses( $user_id ) {
		global $wpdb;
		$ids = array();
		$payments = $this->get_payments( array(
			'user_id'        => $user_id,
			'payment_status' => array( 'pending' ),
		), OBJECT_K );

		if ( ! empty( $payments ) ) {
			$payment_ids = array();

			foreach ( $payments as $payment ) {
				$ids[] = $payment->course_id;
				$payment_ids[ $payment->course_id ] = $payment->ID;
			}

			$query = new WP_Query( array(
				'post_type'      => 'ib_educator_course',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'order'          => 'ASC',
			) );

			if ( $query->have_posts() ) {
				$posts = array();

				foreach ( $query->posts as $post ) {
					$post->edu_payment_id = $payment_ids[ $post->ID ];
					$post->edu_payment = $payments[ $post->edu_payment_id ];
					$posts[ $post->ID ] = $post;
				}

				return $posts;
			}
		}

		return false;
	}

	/**
	 * Get lessons for a course.
	 *
	 * @param int $course_id
	 * @return false|WP_Query
	 */
	public function get_lessons( $course_id ) {
		if ( ! is_numeric( $course_id ) ) {
			return false;
		}

		return new WP_Query( array(
			'post_type'      => 'ib_educator_lesson',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array( 'key' => '_ibedu_course', 'value' => $course_id, 'compare' => '=' )
			)
		) );
	}

	/**
	 * Get the number of lessons in a course.
	 *
	 * @param int $course_id
	 * @return int
	 */
	public function get_num_lessons( $course_id ) {
		global $wpdb;

		$num_lessons = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.post_type='ib_educator_lesson' AND pm.meta_key='_ibedu_course' AND pm.meta_value=%d",
				$course_id
			)
		);

		return $num_lessons;
	}

	/**
	 * Get payments.
	 *
	 * @param array $args
	 * @return array
	 */
	public function get_payments( $args, $output_type = null ) {
		global $wpdb;

		if ( is_null( $output_type ) ) {
			$output_type = OBJECT;
		}

		$sql = 'SELECT * FROM ' . $this->payments . ' WHERE 1';

		// Filter by payment_id.
		if ( isset( $args['payment_id'] ) ) {
			if ( is_array( $args['payment_id'] ) ) {
				$sql .= ' AND ID IN (' . implode( ',', array_map( 'absint', $args['payment_id'] ) ) . ')';
			} else {
				$sql .= $wpdb->prepare( ' AND ID = %d', $args['payment_id'] );
			}
		}

		// Filter by user_id.
		if ( isset( $args['user_id'] ) ) {
			$sql .= $wpdb->prepare( ' AND user_id = %d', $args['user_id'] );
		}

		// Filter by course_id.
		if ( isset( $args['course_id'] ) ) {
			$sql .= $wpdb->prepare( ' AND course_id = %d', $args['course_id'] );
		}

		// Filter by payment_type.
		if ( isset( $args['payment_type'] ) ) {
			$sql .= $wpdb->prepare( ' AND payment_type = %s', $args['payment_type'] );
		}

		// Filter by object_id.
		if ( isset( $args['object_id'] ) ) {
			$sql .= $wpdb->prepare( ' AND object_id = %d', $args['object_id'] );
		}

		// Filter by payment status.
		if ( isset( $args['payment_status'] ) && is_array( $args['payment_status'] ) ) {
			$sql .= $wpdb->prepare(
				' AND payment_status IN (' . implode( ',', array_fill( 0, count( $args['payment_status'] ), '%s' ) ) . ')',
				$args['payment_status']
			);
		}

		// With or without pagination
		$has_pagination = ( isset( $args['page'] ) && isset( $args['per_page'] )
			&& is_numeric( $args['page'] ) && is_numeric( $args['per_page'] ) );
		$pagination_sql = '';

		if ( $has_pagination ) {
			$num_rows = $wpdb->get_var( str_replace( 'SELECT *', 'SELECT count(1)', $sql ) );
			$pagination_sql .= ' LIMIT ' . ( ( $args['page'] - 1 ) * $args['per_page'] ) . ', ' . $args['per_page'];
		}

		$payments = $wpdb->get_results( $sql . ' ORDER BY payment_date DESC' . $pagination_sql, $output_type );

		if ( ! empty( $payments ) ) {
			$payments = array_map( 'edr_get_payment', $payments );
		}

		if ( $has_pagination ) {
			return array(
				'num_pages' => ceil( $num_rows / $args['per_page'] ),
				'num_items' => $num_rows,
				'rows'      => $payments,
			);
		}

		return $payments;
	}

	/**
	 * Get payments count groupped by payment status.
	 *
	 * @deprecated 1.3.0
	 */
	public function get_payments_count() {
		global $wpdb;

		return $wpdb->get_results( "SELECT payment_status, count(1) as num_rows FROM {$this->payments} GROUP BY payment_status", OBJECT_K );
	}

	/**
	 * Get courses of a lecturer.
	 *
	 * @param int $user_id
	 * @return array
	 */
	public function get_lecturer_courses( $user_id ) {
		global $wpdb;

		return $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_author=%d AND post_type='ib_educator_course'", $user_id ) );
	}

	/**
	 * Get quiz questions.
	 *
	 * @param array $args
	 * @return false|array of IB_Educator_Question objects
	 */
	public function get_questions( $args ) {
		_ib_edu_deprecated_function( 'IB_Educator::get_questions', '1.6', 'Edr_Quizzes::get_questions' );

		return Edr_Manager::get( 'edr_quizzes' )->get_questions( $args['lesson_id'] );
	}

	/**
	 * Get all answers choices for a lesson.
	 *
	 * @param int $lesson_id
	 * @return false|array
	 */
	public function get_choices( $lesson_id, $sorted = false ) {
		_ib_edu_deprecated_function( 'IB_Educator::get_choices', '1.6', 'Edr_Quizzes::get_choices' );

		return Edr_Manager::get( 'edr_quizzes' )->get_choices( $lesson_id, $sorted );
	}

	/**
	 * Get the available choices for a multiple answer question.
	 *
	 * @param int $question_id
	 * @return false|array
	 */
	public function get_question_choices( $question_id ) {
		_ib_edu_deprecated_function( 'IB_Educator::get_question_choices', '1.6', 'Edr_Quizzes::get_question_choices' );

		return Edr_Manager::get( 'edr_quizzes' )->get_question_choices( $question_id );
	}

	/**
	 * Add question answer choice to the database.
	 *
	 * @param array $data
	 * @return false|int
	 */
	public function add_choice( $data ) {
		_ib_edu_deprecated_function( 'IB_Educator::add_choice', '1.6', 'Edr_Quizzes::add_choice' );

		return Edr_Manager::get( 'edr_quizzes' )->add_choice( $data );
	}

	/**
	 * Update question answer choice in the database.
	 *
	 * @param array $data
	 * @return false|int
	 */
	public function update_choice( $choice_id, $data ) {
		_ib_edu_deprecated_function( 'IB_Educator::update_choice', '1.6', 'Edr_Quizzes::update_choice' );

		return Edr_Manager::get( 'edr_quizzes' )->update_choice( $choice_id, $data );
	}

	/**
	 * Delete question answer choice from the database.
	 *
	 * @param int $choice_id
	 * @return false|int false on error, number of rows updated on success.
	 */
	public function delete_choice( $choice_id ) {
		_ib_edu_deprecated_function( 'IB_Educator::delete_choice', '1.6', 'Edr_Quizzes::delete_choice' );

		return Edr_Manager::get( 'edr_quizzes' )->delete_choice( $choice_id );
	}

	/**
	 * Delete question answer choices from the database.
	 *
	 * @param int $question_id
	 * @return false|int false on error, number of rows updated on success.
	 */
	public function delete_choices( $question_id ) {
		_ib_edu_deprecated_function( 'IB_Educator::delete_choices', '1.6', 'Edr_Quizzes::delete_choices' );

		return Edr_Manager::get( 'edr_quizzes' )->delete_choices( $question_id );
	}

	/**
	 * Add answer to a question in a quiz.
	 *
	 * @param array $data
	 * @return false|int
	 */
	public function add_student_answer( $data ) {
		_ib_edu_deprecated_function( 'IB_Educator::add_student_answer', '1.6', 'Edr_Quizzes::add_answer' );

		return Edr_Manager::get( 'edr_quizzes' )->add_answer( $data );
	}

	/**
	 * Get student's answers for a given lesson.
	 *
	 * @param int $lesson_id
	 * @param int $entry_id
	 * @return false|array
	 */
	public function get_student_answers( $lesson_id, $entry_id ) {
		_ib_edu_deprecated_function( 'IB_Educator::get_student_answers', '1.6', 'Edr_Quizzes::get_answers( int $grade_id )' );

		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT question_id, ID, entry_id, question_id, choice_id, correct, answer_text "
				. "FROM {$this->answers} "
				. "WHERE entry_id = %d AND question_id IN (SELECT question_id FROM {$this->questions} WHERE lesson_id = %d)",
				$entry_id,
				$lesson_id
			),
			OBJECT_K
		);
	}

	/**
	 * Add grade for a quiz.
	 *
	 * @param array $data
	 * @return false|int
	 */
	public function add_quiz_grade( $data ) {
		_ib_edu_deprecated_function( 'IB_Educator::add_quiz_grade', '1.6', 'Edr_Quizzes::add_grade' );

		return Edr_Manager::get( 'edr_quizzes' )->add_grade( $data );
	}

	/**
	 * Update quiz grade.
	 *
	 * @param array $data
	 * @return int
	 */
	public function update_quiz_grade( $grade_id, $data ) {
		_ib_edu_deprecated_function( 'IB_Educator::update_quiz_grade', '1.6', 'Edr_Quizzes::update_grade' );

		return Edr_Manager::get( 'edr_quizzes' )->update_grade( $grade_id, $data );
	}

	/**
	 * Check if the quiz was submitted for a given lesson.
	 *
	 * @param int $lesson_id
	 * @param int $entry_id
	 * @return boolean
	 */
	public function is_quiz_submitted( $lesson_id, $entry_id ) {
		_ib_edu_deprecated_function( 'IB_Educator::is_quiz_submitted', '1.6', 'Edr_Quizzes::get_grade( int $lesson_id, int $entry_id )' );

		global $wpdb;

		$submitted = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(1) FROM {$this->grades} WHERE lesson_id=%d AND entry_id=%d LIMIT 1",
			$lesson_id,
			$entry_id
		) );

		return ( 1 == $submitted );
	}

	/**
	 * Get student's grade for the given quiz.
	 *
	 * @param int $lesson_id
	 * @param int $entry_id
	 * @return array
	 */
	public function get_quiz_grade( $lesson_id, $entry_id ) {
		_ib_edu_deprecated_function( 'IB_Educator::get_quiz_grade', '1.6', 'Edr_Quizzes::get_grade' );

		return Edr_Manager::get( 'edr_quizzes' )->get_grade( $lesson_id, $entry_id );
	}

	/**
	 * Get the entries with ungraded quizzes.
	 *
	 * @param array $ids
	 * @return array
	 */
	public function check_quiz_pending( $ids ) {
		_ib_edu_deprecated_function( 'IB_Educator::check_quiz_pending', '1.6', 'Edr_Quizzes::check_for_pending_quizzes' );

		return Edr_Manager::get( 'edr_quizzes' )->check_for_pending_quizzes( $ids );
	}

	/**
	 * Get the course prerequisites.
	 *
	 * @param int $course_id
	 * @return array
	 */
	public function get_prerequisites( $course_id ) {
		$prerequisites = get_post_meta( $course_id, '_ib_educator_prerequisites', true );

		if ( ! is_array( $prerequisites ) ) {
			$prerequisites = array();
		}

		return $prerequisites;
	}

	/**
	 * Check if a user has completed the required course prerequisites.
	 *
	 * @param int $course_id
	 * @param int $user_id
	 * @return bool
	 */
	public function check_prerequisites( $course_id, $user_id ) {
		$prerequisites = $this->get_prerequisites( $course_id );

		if ( empty( $prerequisites ) ) {
			return true;
		}

		$completed_courses = $this->get_entries( array(
			'user_id'      => $user_id,
			'entry_status' => 'complete',
		) );

		if ( empty( $completed_courses ) ) {
			// The user has no courses completed.
			return false;
		}

		$prerequisites_satisfied = 0;

		foreach ( $completed_courses as $entry ) {
			if ( in_array( $entry->course_id, $prerequisites ) ) {
				$prerequisites_satisfied += 1;
			}
		}

		return ( $prerequisites_satisfied == count( $prerequisites ) );
	}

	/**
	 * Setup payment item (e.g. course, membership).
	 *
	 * @param IB_Educator_Payment $payment
	 */
	public function setup_payment_item( $payment ) {
		if ( 'course' == $payment->payment_type ) {
			// Setup course entry.
			$entry = $this->get_entry( array( 'payment_id' => $payment->ID ) );

			if ( ! $entry ) {
				$entry = edr_get_entry();
				$entry->course_id = $payment->course_id;
				$entry->user_id = $payment->user_id;
				$entry->payment_id = $payment->ID;
				$entry->entry_status = 'inprogress';
				$entry->entry_date = date( 'Y-m-d H:i:s' );
				$entry->save();

				// Send notification email to the student.
				$student = get_user_by( 'id', $payment->user_id );
				$course = get_post( $payment->course_id, OBJECT, 'display' );

				if ( $student && $course ) {
					ib_edu_send_notification(
						$student->user_email,
						'student_registered',
						array(
							'course_title' => $course->post_title,
						),
						array(
							'student_name'   => $student->display_name,
							'course_title'   => $course->post_title,
							'course_excerpt' => $course->post_excerpt,
						)
					);
				}
			}
		} elseif ( 'membership' == $payment->payment_type ) {
			// Setup membership.
			$ms = Edr_Memberships::get_instance();
			$ms->setup_membership( $payment->user_id, $payment->object_id );

			$student = get_user_by( 'id', $payment->user_id );
			$membership = $ms->get_membership( $payment->object_id );

			if ( $student && $membership ) {
				$user_membership = $ms->get_user_membership( $student->ID );
				$membership_meta = $ms->get_membership_meta( $membership->ID );
				$expiration = ( $user_membership ) ? $user_membership['expiration'] : 0;

				ib_edu_send_notification(
					$student->user_email,
					'membership_register',
					array(),
					array(
						'student_name' => $student->display_name,
						'membership'   => $membership->post_title,
						'expiration'   => ( $expiration ) ? date_i18n( get_option( 'date_format' ), $expiration ) : __( 'None', 'ibeducator' ),
						'price'        => $ms->format_price( $membership_meta['price'], $membership_meta['duration'], $membership_meta['period'], false ),
					)
				);
			}
		}
	}

	/**
	 * Get billing data for a user.
	 *
	 * @param int $user_id
	 * @return array
	 */
	public function get_billing_data( $user_id ) {
		$billing = get_user_meta( $user_id, '_ib_educator_billing', true );

		if ( ! is_array( $billing ) ) {
			$billing = array(
				'address'    => '',
				'address_2'  => '',
				'city'       => '',
				'state'      => '',
				'postcode'   => '',
				'country'    => '',
			);
		}

		return $billing;
	}
}

class IBEdu_API {
	public static function get_instance() {
		_ib_edu_deprecated_function( 'IBEdu_API::get_instance()', '0.9.0', 'IB_Educator::get_instance()' );
		return IB_Educator::get_instance();
	}
}
