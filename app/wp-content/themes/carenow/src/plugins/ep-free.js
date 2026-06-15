/**
 * WordPress dependencies.
 */
import { CheckboxControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginPostStatusInfo } from '@wordpress/edit-post';

export default () => {
    const { editPost } = useDispatch('core/editor');

    const { ep_free = 0, ...meta } = useSelect(
        (select) => select('core/editor').getEditedPostAttribute('meta') || {},
    );

    const onChange = (ep_free) => {
        console.log(ep_free);
        editPost({ meta: { ...meta, ep_free } });
    };

    return (
        <PluginPostStatusInfo>
            <CheckboxControl
                name={'ep_free'}
                label={'Бесплатная статья'}
                help={'Бесплатные статьи доступны всем пользователям, вне зависимости от наличия подписки.'}
                checked={ep_free}
                onChange={onChange}
            />
        </PluginPostStatusInfo>
    );
};