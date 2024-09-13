#!groovy
#krishna

#test-demo
pipeline {
    agent any
    
    stages {
        stage('Clone Repository') {
            steps {
                git 'https://github.com/krishnareddypadala/phpvulnbank.git'
            }
        }
        
        stage('Fortify Scan') {
            steps {
                fodStaticAssessment applicationName: '', 
                
                applicationType: '', assessmentType: '', 
                
                attributes: '', 
                
                auditPreference: '', 
                
                bsiToken: '', 
                
                businessCriticality: '', 
                
                entitlementId: '', 
                
                entitlementPreference: '', 
                
                frequencyId: '', 
                
                inProgressBuildResultType: 'FailBuild', 
                
                inProgressScanActionType: 'Queue', 
                
                isMicroservice: false, 
                
                languageLevel: '', 
                
                microserviceName: '', 
                
                openSourceScan: '', 
                
                overrideGlobalConfig: false, 
                
                personalAccessToken: '', 
                
                releaseId: '', //provide release id here
                
                releaseName: '', 
                
                remediationScanPreferenceType: 'RemediationScanIfAvailable', 
                
                scanCentral: 'PHP', 
                
                scanCentralBuildCommand: '', 
                
                scanCentralBuildFile: '', 
                
                scanCentralBuildToolVersion: '', 
                
                scanCentralIncludeTests: '', 
                
                scanCentralRequirementFile: '', 
                
                scanCentralSkipBuild: '', 
                
                scanCentralVirtualEnv: '', 
                
                sdlcStatus: '', 
                
                srcLocation: '', 
                
                technologyStack: '', 
                
                tenantId: '', 
                
                username: ''
            }
        }
      
    }
}
 
