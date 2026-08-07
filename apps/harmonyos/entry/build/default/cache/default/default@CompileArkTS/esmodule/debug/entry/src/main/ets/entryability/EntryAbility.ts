import type AbilityConstant from "@ohos:app.ability.AbilityConstant";
import type Want from "@ohos:app.ability.Want";
import UIAbility from "@ohos:app.ability.UIAbility";
import type window from "@ohos:window";
import { AppState } from "@bundle:com.erik.shop/entry/ets/store/AppState";
export default class EntryAbility extends UIAbility {
    onCreate(want: Want, launchParam: AbilityConstant.LaunchParam): void {
        AppState.getInstance().init(this.context);
    }
    onWindowStageCreate(windowStage: window.WindowStage) {
        windowStage.loadContent('pages/Index', (err, data) => {
            if (err.code) {
                console.error('Failed to load content: ' + JSON.stringify(err));
                return;
            }
        });
    }
}
