/**
 * WordPress dependencies.
 */
import { __experimentalNumberControl as NumberControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginPostStatusInfo } from '@wordpress/edit-post';

export default () => {
    const { editPost } = useDispatch('core/editor');

    const { ep_order = 0, ...meta } = useSelect(
        (select) => select('core/editor').getEditedPostAttribute('meta') || {},
    );

    const onChange = (ep_order) => {
        console.log(ep_order);
        editPost({ meta: { ...meta, ep_order } });
    };

    return (
        <PluginPostStatusInfo>
            <NumberControl
                name={'ep_order'}
                label={'Порядок'}
                help={'От 0 до 9. Где 9 самый высокий и будет подсвечен в быстром поиске.'}
                value={ep_order}
                min={0}
                max={9}
                onChange={onChange}
            />
        </PluginPostStatusInfo>
    );
};