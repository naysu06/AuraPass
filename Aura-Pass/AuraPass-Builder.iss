; ============================================================
;  AuraPass - Inno Setup Script
;  Stack: Laragon Portable + Laravel + Apache + MySQL
;  Target: Non-technical end users (Windows)
; ============================================================

#define AppName      "AuraPass"
#define AppVersion   "1.0.1"
#define AppPublisher "AuraPass"
#define AppURL       "http://localhost:8000"

; ── Version subfolders (update if Laragon versions change)
#define ApacheDir    "httpd-2.4.62-240904-win64-VS17"
#define MySQLDir     "mysql-8.4.3-winx64"
#define PHPDir       "php-8.3.26-Win32-vs16-x64"

[Setup]
AppId={{A1B2C3D4-E5F6-7890-ABCD-EF1234567890}
AppName={#AppName}
AppVersion={#AppVersion}
AppVerName={#AppName} {#AppVersion}
AppPublisherURL={#AppURL}
AppSupportURL={#AppURL}
AppUpdatesURL={#AppURL}
DefaultDirName=C:\{#AppName}
DisableDirPage=yes
DefaultGroupName={#AppName}
AllowNoIcons=no
LicenseFile=LICENSE.txt
OutputDir=dist
OutputBaseFilename=AuraPass-Setup-v{#AppVersion}
SetupIconFile=assets\icon.ico
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
MinVersion=10.0
UninstallDisplayIcon={app}\assets\icon.ico
UninstallDisplayName={#AppName}
ChangesEnvironment=no
DisableProgramGroupPage=no

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Create a &Desktop shortcut"; GroupDescription: "Additional icons:"

[Dirs]
; Base Storage & App
Name: "{app}\storage"; Permissions: everyone-full
Name: "{app}\storage\app"; Permissions: everyone-full
Name: "{app}\storage\app\public"; Permissions: everyone-full
Name: "{app}\storage\app\public\member-photos"; Permissions: everyone-full
Name: "{app}\storage\app\livewire-tmp"; Permissions: everyone-full

; Framework & Cache
Name: "{app}\storage\framework"; Permissions: everyone-full
Name: "{app}\storage\framework\cache"; Permissions: everyone-full
Name: "{app}\storage\framework\cache\data"; Permissions: everyone-full
Name: "{app}\storage\framework\sessions"; Permissions: everyone-full
Name: "{app}\storage\framework\views"; Permissions: everyone-full
Name: "{app}\bootstrap\cache"; Permissions: everyone-full

; Logs & Database
Name: "{app}\storage\logs"; Permissions: everyone-full
Name: "{app}\laragon\bin\mysql\{#MySQLDir}\data"; Permissions: everyone-full

[Files]
; ── Laravel Application ──────────────────────────────────
Source: "app\*";           DestDir: "{app}\app";        Flags: recursesubdirs createallsubdirs ignoreversion
Source: "bootstrap\*";     DestDir: "{app}\bootstrap";  Flags: recursesubdirs createallsubdirs ignoreversion
Source: "config\*";        DestDir: "{app}\config";     Flags: recursesubdirs createallsubdirs ignoreversion
Source: "database\*";      DestDir: "{app}\database";   Flags: recursesubdirs createallsubdirs ignoreversion
Source: "public\*";        DestDir: "{app}\public";     Flags: recursesubdirs createallsubdirs ignoreversion
Source: "resources\*";     DestDir: "{app}\resources";  Flags: recursesubdirs createallsubdirs ignoreversion
Source: "routes\*";        DestDir: "{app}\routes";     Flags: recursesubdirs createallsubdirs ignoreversion
Source: "vendor\*";        DestDir: "{app}\vendor";     Flags: recursesubdirs createallsubdirs ignoreversion
Source: "artisan";         DestDir: "{app}";            Flags: ignoreversion
Source: "composer.json";   DestDir: "{app}";            Flags: ignoreversion
Source: ".env.production"; DestDir: "{app}"; DestName: ".env"; Flags: ignoreversion

; ── Laragon Portable Stack ───────────────────────────────
Source: "C:\laragon\bin\apache\{#ApacheDir}\*"; DestDir: "{app}\laragon\bin\apache\{#ApacheDir}"; Flags: recursesubdirs createallsubdirs ignoreversion
; Copy everything EXCEPT the data folder
Source: "C:\laragon\bin\mysql\{#MySQLDir}\*"; DestDir: "{app}\laragon\bin\mysql\{#MySQLDir}"; Flags: recursesubdirs createallsubdirs ignoreversion; Excludes: "data\*"
; Copy the data folder ONLY if it doesn't already exist
Source: "C:\laragon\data\mysql-8.4\*"; DestDir: "{app}\laragon\bin\mysql\{#MySQLDir}\data"; Flags: recursesubdirs createallsubdirs uninsneveruninstall onlyifdoesntexist
Source: "C:\laragon\bin\php\{#PHPDir}\*";       DestDir: "{app}\laragon\bin\php\{#PHPDir}";       Flags: recursesubdirs createallsubdirs ignoreversion

; ── Config Files ─────────────────────────────────────────
Source: "installer\httpd.conf"; DestDir: "{app}\laragon\bin\apache\{#ApacheDir}\conf"; Flags: ignoreversion
Source: "installer\php.ini";    DestDir: "{app}\laragon\bin\php\{#PHPDir}";            Flags: ignoreversion
Source: "installer\my.ini";     DestDir: "{app}\laragon\bin\mysql\{#MySQLDir}";        Flags: ignoreversion

; ── Launcher & Helper Scripts ────────────────────────────
Source: "AuraPass-Start.bat";   DestDir: "{app}"; Flags: ignoreversion
Source: "AuraPass-Stop.bat";    DestDir: "{app}"; Flags: ignoreversion

; ── Assets ───────────────────────────────────────────────
Source: "assets\icon.ico"; DestDir: "{app}\assets"; Flags: ignoreversion
Source: "LICENSE.txt";     DestDir: "{app}";        Flags: ignoreversion

[Icons]
Name: "{group}\{#AppName}";             Filename: "{app}\AuraPass-Start.bat"; IconFilename: "{app}\assets\icon.ico"; Comment: "Launch AuraPass"; Flags: runminimized
Name: "{group}\Stop {#AppName}";        Filename: "{app}\AuraPass-Stop.bat";  IconFilename: "{app}\assets\icon.ico"; Comment: "Stop AuraPass"; Flags: runminimized
Name: "{group}\Uninstall {#AppName}";   Filename: "{uninstallexe}"

Name: "{autodesktop}\{#AppName}";       Filename: "{app}\AuraPass-Start.bat"; IconFilename: "{app}\assets\icon.ico"; Tasks: desktopicon; Flags: runminimized

[Run]
; 1. Create the Symlink during Installation (Ensures it works out of the box)
Filename: "{app}\laragon\bin\php\{#PHPDir}\php.exe"; \
  Parameters: """{app}\artisan"" storage:link --force"; \
  WorkingDir: "{app}"; \
  Flags: runhidden waituntilterminated; \
  StatusMsg: "Linking public storage assets..."

; 2. Optimize Laravel
Filename: "{app}\laragon\bin\php\{#PHPDir}\php.exe"; \
  Parameters: """{app}\artisan"" optimize"; \
  WorkingDir: "{app}"; \
  Flags: runhidden waituntilterminated; \
  StatusMsg: "Optimizing system performance..."

; 3. Launch AuraPass
Filename: "{cmd}"; \
  Parameters: "/c ""{app}\AuraPass-Start.bat"""; \
  Description: "Launch {#AppName} now"; \
  Flags: postinstall nowait skipifsilent runhidden

[UninstallRun]
Filename: "{app}\AuraPass-Stop.bat"; \
  RunOnceId: "StopServers"; \
  Flags: runhidden waituntilterminated

[Code]
procedure InitializeWizard;
begin
  WizardForm.WelcomeLabel2.Caption :=
    'This will install AuraPass on your computer.' + #13#10 + #13#10 +
    'AuraPass includes its own web server and database.' + #13#10 +
    'No technical knowledge is required.' + #13#10 + #13#10 +
    'Click Next to continue.';
end;