<?php

namespace ACA\SimplePayment;

use AC;
use AC\Asset\Script;
use AC\Asset\Style;
use AC\Services;
use AC\Registerable;
use ACA\SimplePayment\Column\EntryConfigurator;
use ACA\SimplePayment\Column\EntryFactory;
use ACA\SimplePayment\ListScreen;
use ACA\SimplePayment\Search\Query;
use ACA\SimplePayment\TableScreen;
use ACP\QueryFactory;
use ACP\Service\IntegrationStatus;


require_once( __DIR__ . '/TableScreen/Entry.php' );
require_once( __DIR__ . '/Admin.php' );
require_once( __DIR__ . '/MetaTypes.php' );
require_once( __DIR__ . '/MetaTypes.php' );
require_once( __DIR__ . '/ListScreen/Entry.php' );

require_once( __DIR__ . '/Column/EntryConfigurator.php' );
require_once( __DIR__ . '/Column/EntryFactory.php' );
require_once( __DIR__ . '/Column/Entry.php' );

require_once( __DIR__ . '/Field.php' );
require_once( __DIR__ . '/FieldFactory.php' );
require_once( __DIR__ . '/FieldTypes.php' );
require_once( __DIR__ . '/Field/Field.php' );

require_once( __DIR__ . '/Search/TableScreen/Entry.php' );
require_once( __DIR__ . '/Search/Query/Bindings.php' );

require_once( __DIR__ . '/Editing/TableRows/Entry.php' );
require_once( __DIR__ . '/Editing/Strategy/Entry.php' );
require_once( __DIR__ . '/Editing/EntryServiceFactory.php' );

require_once( __DIR__ . '/HideOnScreen/EntryFilters.php' );
require_once( __DIR__ . '/HideOnScreen/WordPressNotifications.php' );

require_once( __DIR__ . '/TableFactory.php' );

require_once( __DIR__ . '/Capabilities.php' );


require_once( __DIR__ . '/Column/Entry/Original/EntryId.php' );


require_once( __DIR__ . '/Export/Model/EntryFactory.php' );
require_once( __DIR__ . '/Export/Model/Entry/Address.php' );
require_once( __DIR__ . '/Export/Model/Entry/Check.php' );
require_once( __DIR__ . '/Export/Model/Entry/ItemList.php' );
require_once( __DIR__ . '/Export/Strategy/Entry.php' );


require_once( __DIR__ . '/Search/Comparison/EntryFactory.php' );
require_once( __DIR__ . '/Search/Comparison/Entry.php' );
require_once( __DIR__ . '/Search/Comparison/Entry/EntryId.php' );

require_once( __DIR__ . '/ListTable.php' );
require_once( __DIR__ . '/ListScreenFactory/EntryFactory.php' );

require_once( __DIR__ . '/Service/ColumnGroup.php' );
require_once( __DIR__ . '/Service/Columns.php' );
require_once( __DIR__ . '/Service/ListScreens.php' );
require_once( __DIR__ . '/Service/Scripts.php' );


final class SimplePayment implements Registerable {

	const GROUP = 'simple-payment';

    private $location;

    private $container;

    public function __construct( AC\Asset\Location\Absolute $location, ContainerInterface $container ) {
        $this->location = $location;
        $this->container = $container;
    }

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_action( 'ac/list_screens', [ $this, 'register_list_screen' ] );

		AC\ListScreenFactory\Aggregate::add( new ListScreenFactory\EntryFactory() );
        $this->create_services()->register();

        ACP\Search\TableScreenFactory::register( ListScreen\Entry::class, Search\TableScreen\Entry::class );
        ACP\Filtering\TableScreenFactory::register( ListScreen\Entry::class, Filtering\Table\Entry::class );

	}

	private function create_services(): Services {
        return new Services([
            new Service\ListScreens(),
            new Service\Columns(),
            new TableScreen\Entry(
                new AC\ListScreenFactory\Aggregate(),
                $this->container->get( AC\ListScreenRepository\Storage::class ),
                new DefaultColumnsRepository()
            ),
            new Admin(),
            new IntegrationStatus( 'ac-addon-simplepayment' ),
            new Scripts( $this->location ),
            new ColumnGroup(),
        ]);
    }

	public function register_list_screen() {
		$list_screen_types = AC\ListScreenTypes::instance();
		if ( ! $list_screen_types ) {
			return;
		}

		$fieldFactory = new FieldFactory();
		$columnFactory = new EntryFactory( new FieldFactory() );

		$configurator = new EntryConfigurator( $columnFactory, $fieldFactory );
		$configurator->register();
		$list_screen_types->register_list_screen( new ListScreen\Entry( $configurator ) );
		
	/*
		$forms = array_merge( SPAPI::get_forms(), SPAPI::get_forms( [ 'active' => false ] ) );

		foreach ( $forms as $form ) {
			$fieldFactory = new FieldFactory();
			$columnFactory = new EntryFactory( new FieldFactory() );

			$configurator = new EntryConfigurator( (int) $form['id'], $columnFactory, $fieldFactory );
			$configurator->register();

			$list_screen_types->register_list_screen( new ListScreen\Entry( $form['id'], $configurator ) );
		}
			*/
	}

}