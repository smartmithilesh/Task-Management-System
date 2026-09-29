# Flutter client

The Android and iOS runner projects are included. Install Flutter stable, run `flutter pub get`, then configure the API address with `flutter run --dart-define=API_BASE_URL=https://your-host.example`. The default points to Android Emulator localhost. The client stores its revocable bearer token with platform secure storage.

The runner identifiers are placeholders (`com.example.task_management_mobile` on Android and `com.example.taskManagementMobile` on iOS); replace them with identifiers owned by your organization before publishing. Android builds need the Android SDK and a compatible JDK (17 through 25 for the generated Gradle configuration). iOS builds require macOS and Xcode.

Release builds require an upload keystore and an ignored `android/key.properties` file containing `storeFile`, `storePassword`, `keyAlias`, and `keyPassword`. Generate an upload key from `mobile/` with `keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload`, then create `android/key.properties` with:

```properties
storeFile=../upload-keystore.jks
storePassword=<your store password>
keyAlias=upload
keyPassword=<your key password>
```

The `storeFile` path is relative to the `android/` directory. Release builds fail if any signing value is missing, and release signing never falls back to the debug key. Keep the keystore and properties file out of version control.

The client includes secure bearer-token sign-in and resume, task list refresh, task details, comments, checklist updates, timer controls, push permission/token registration, task notification taps, and sign-out with local credential removal. Configure Firebase native app files for each build flavor and the backend Firebase service-account settings before push is enabled. See [push notification setup](../docs/api/push-notifications.md).

Run `flutter pub get`, `flutter analyze`, and `flutter test` after changing the client. These checks pass with Flutter 3.47.5 and Dart 3.13.4. An Android debug APK was built successfully in this workspace. Android and iOS release builds still need the application identifiers and signing/Firebase setup described above; iOS builds require macOS and Xcode.
