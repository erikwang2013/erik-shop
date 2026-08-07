/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import { AbilityConstant, Want } from '@kit.AbilityKit';
import UIAbility from '@ohos.app.ability.UIAbility';
import window from '@ohos.window';
import { AppState } from '../store/AppState';

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
