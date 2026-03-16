import java.io.FileInputStream
import java.util.Properties

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("key.properties")
if (keystorePropertiesFile.exists()) {
    keystoreProperties.load(FileInputStream(keystorePropertiesFile))
}

android {
    namespace = "com.example.smart_timetable_application"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = "27.0.12077973"

    flavorDimensions += "app"

    compileOptions {
        // Required by flutter_local_notifications for scheduled notifications on Android 5–11
        isCoreLibraryDesugaringEnabled = true
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_11.toString()
    }

    defaultConfig {
        applicationId = "za.ac.ump.smarttimetable"
        minSdk = 21          // Android 5.0 — covers essentially all active Android phones
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = "1.0.0"
        manifestPlaceholders["appName"] = "Smart Timetable"
    }

    productFlavors {
        create("student") {
            dimension = "app"
            manifestPlaceholders["appName"] = "Smart Timetable"
        }
        create("lecturer") {
            dimension = "app"
            applicationIdSuffix = ".lecturer"
            manifestPlaceholders["appName"] = "Smart Timetable Lecturer"
        }
    }

    signingConfigs {
        create("release") {
            val storeFilePath = keystoreProperties.getProperty("storeFile")
            if (!storeFilePath.isNullOrBlank()) {
                storeFile = file(storeFilePath)
            }
            storePassword = keystoreProperties.getProperty("storePassword")
            keyAlias = keystoreProperties.getProperty("keyAlias")
            keyPassword = keystoreProperties.getProperty("keyPassword")
        }
    }

    buildTypes {
        release {
            // Use production keystore if key.properties exists; otherwise fallback to debug signing.
            signingConfig = if (keystorePropertiesFile.exists()) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }
}

flutter {
    source = "../.."
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}
