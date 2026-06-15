/**
 * WordPress dependencies.
 */
import {registerPlugin} from '@wordpress/plugins';

/**
 * Internal dependencies.
 */
import Tree_Position from './plugins/tree-position';
import EP_Free from './plugins/ep-free';

registerPlugin('tree-position', {
    render: Tree_Position
});

registerPlugin('ep-free', {
    render: EP_Free
});

