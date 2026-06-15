/**
 * WordPress dependencies.
 */
import { PluginPostStatusInfo } from '@wordpress/edit-post';
import { PanelRow, __experimentalText as Text } from '@wordpress/components';

const { Component } = wp.element;
const { Spinner } = wp.components;

//https://medvisement.com/wp-json/medvise-vt/v1/tree/plain/67

class PlainTree extends Component {
    constructor(props) {
        super(props);
        this.state = {
            list: [],
            loading: true
        }
    }

    componentDidMount() {
        this.runApiFetch();
    }

    runApiFetch() {
        const post_id = wp.data.select("core/editor").getCurrentPostId();

        wp.apiFetch({
            path: `medvise-vt/v1/tree/plain/${post_id}`,
        }).then(data => {
            this.setState({
                data: data,
                loading: false
            });
        });
    }

    render() {

        let content;

        if (this.state.loading) {
            content = (<Spinner/>);
        } else if (this.state.data.status == 'error') {
            content = ('Ошибка получения древа');
        } else if (this.state.data.plain_tree.length == 0) {
            content = ('Запись отсутствует в древе');
        }
        else {
            content = this.state.data.plain_tree;
        }

        return (
            <div>
                Древовидность <br/>
                <span>{content}</span>
            </div>
        );

    }
}

export default () => {

    const post_type = wp.data.select("core/editor").getCurrentPostType();

    if ( ! ['disease', 'substance'].includes(post_type) ) {
        return '';
    }

    return (
        <PluginPostStatusInfo>
            <PanelRow className={"edit-post-post-tree-position"}>
                <PlainTree></PlainTree>
            </PanelRow>
        </PluginPostStatusInfo>
    );

}