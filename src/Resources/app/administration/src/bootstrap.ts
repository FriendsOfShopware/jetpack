import './service/frosh-jetpack-configuration.api.service';
import './component/jetpack-ui';
import './component/frosh-jetpack-field';
import './module/frosh-jetpack-configuration';
import './administration-crud/runtime';
import './administration-crud/listing/listing.scss';
import './administration-crud/detail/detail.scss';
import { installFroshJetpackGlobal } from './administration-crud/install-global';

export function bootstrap(): void {
    installFroshJetpackGlobal();
}
