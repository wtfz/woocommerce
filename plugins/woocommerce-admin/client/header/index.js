/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useLayoutEffect, useRef } from '@wordpress/element';
import classnames from 'classnames';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { ONBOARDING_STORE_NAME } from '@woocommerce/data';
import { Text, useSlot } from '@woocommerce/experimental';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import './style.scss';
import useIsScrolled from '../hooks/useIsScrolled';
import {
	WooHeaderNavigationItem,
	WooHeaderItem,
	WooHeaderPageTitle,
} from './utils';
import { TasksReminderBar } from '../tasks';

export const PAGE_TITLE_FILTER = 'woocommerce_admin_header_page_title';

export const Header = ( { sections, isEmbedded = false, query } ) => {
	const headerElement = useRef( null );
	const siteTitle = getSetting( 'siteTitle', '' );
	const pageTitle = sections.slice( -1 )[ 0 ];
	const isScrolled = useIsScrolled();
	let debounceTimer = null;

	const className = classnames( 'woocommerce-layout__header', {
		'is-scrolled': isScrolled,
	} );

	const pageTitleSlot = useSlot( 'woocommerce_header_page_title' );
	const hasPageTitleFills = Boolean( pageTitleSlot?.fills?.length );
	const headerItemSlot = useSlot( 'woocommerce_header_item' );
	const headerItemSlotFills = headerItemSlot?.fills;

	useLayoutEffect( () => {
		updateBodyMargin();
		window.addEventListener( 'resize', updateBodyMargin );
		return () => {
			window.removeEventListener( 'resize', updateBodyMargin );
			const wpBody = document.querySelector( '#wpbody' );

			if ( ! wpBody ) {
				return;
			}

			wpBody.style.marginTop = null;
		};
	}, [ headerItemSlotFills ] );

	const updateBodyMargin = () => {
		clearTimeout( debounceTimer );
		debounceTimer = setTimeout( function () {
			const wpBody = document.querySelector( '#wpbody' );

			if ( ! wpBody || ! headerElement.current ) {
				return;
			}

			wpBody.style.marginTop = `${ headerElement.current.offsetHeight }px`;
		}, 200 );
	};

	useEffect( () => {
		if ( ! isEmbedded ) {
			const documentTitle = sections
				.map( ( section ) => {
					return Array.isArray( section ) ? section[ 1 ] : section;
				} )
				.reverse()
				.join( ' &lsaquo; ' );

			const decodedTitle = decodeEntities(
				sprintf(
					/* translators: 1: document title. 2: page title */
					__(
						'%1$s &lsaquo; %2$s &#8212; WooCommerce',
						'woocommerce'
					),
					documentTitle,
					siteTitle
				)
			);

			if ( document.title !== decodedTitle ) {
				document.title = decodedTitle;
			}
		}
	}, [ isEmbedded, sections, siteTitle ] );

	const { activeSetuplist } = useSelect( ( select ) => {
		const taskLists = select( ONBOARDING_STORE_NAME ).getTaskLists();

		const visibleSetupList = taskLists
			.filter( ( list ) => list.isVisible )
			.filter( ( list ) =>
				[
					'setup_experiment_1',
					'setup_experiment_2',
					'setup',
				].includes( list.id )
			);

		return {
			activeSetuplist: visibleSetupList.length
				? visibleSetupList[ 0 ].id
				: null,
		};
	} );

	return (
		<div className={ className } ref={ headerElement }>
			{ activeSetuplist && (
				<TasksReminderBar
					updateBodyMargin={ updateBodyMargin }
					taskListId={ activeSetuplist }
				/>
			) }
			<div className="woocommerce-layout__header-wrapper">
				<WooHeaderNavigationItem.Slot
					fillProps={ { isEmbedded, query } }
				/>

				<Text
					className={ `woocommerce-layout__header-heading` }
					as="h1"
				>
					{ decodeEntities(
						hasPageTitleFills ? (
							<WooHeaderPageTitle.Slot
								fillProps={ { isEmbedded, query } }
							/>
						) : (
							pageTitle
						)
					) }
				</Text>

				<WooHeaderItem.Slot fillProps={ { isEmbedded, query } } />
			</div>
		</div>
	);
};
