/**
 * LessonItem – Draggable lesson row for the Course Editor sorter.
 *
 * @package
 */

import { Reorder, useDragControls } from 'motion/react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * A single draggable lesson row.
 *
 * @param {Object}   props
 * @param {number}   props.value    Lesson ID (used by Reorder.Item).
 * @param {string}   props.title    Lesson title.
 * @param {Function} props.onRemove Callback to remove this lesson.
 * @return {JSX.Element} The rendered component.
 */
const LessonItem = ( { value, title, onRemove } ) => {
	const controls = useDragControls();

	return (
		<Reorder.Item
			value={ value }
			dragListener={ false }
			dragControls={ controls }
			className="slms-lesson-item"
		>
			<span
				className="slms-drag-handle"
				onPointerDown={ ( e ) => controls.start( e ) }
				role="button"
				tabIndex={ 0 }
				aria-label={ __( 'Drag to reorder', 'simple-lms-bridge' ) }
			>
				⠿
			</span>
			<span className="slms-lesson-title">{ title }</span>
			<Button
				variant="tertiary"
				isDestructive
				size="small"
				onClick={ onRemove }
				aria-label={ __( 'Remove lesson', 'simple-lms-bridge' ) }
			>
				✕
			</Button>
		</Reorder.Item>
	);
};

export default LessonItem;
