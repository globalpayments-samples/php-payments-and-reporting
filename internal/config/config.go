package config

import (
	"github.com/spf13/viper"
	"github.com/go-playground/validator/v10"
)

type Config struct {
	// GP-API Configuration
	GPApiAppID      string `mapstructure:"GP_API_APP_ID"`
	GPApiAppKey     string `mapstructure:"GP_API_APP_KEY"`
	GPApiEnvironment string `mapstructure:"GP_API_ENVIRONMENT"`
	GPApiCountry    string `mapstructure:"GP_API_COUNTRY"`
	GPApiCurrency   string `mapstructure:"GP_API_CURRENCY"`
	GPApiMerchantID string `mapstructure:"GP_API_MERCHANT_ID"`
	
	// Application Configuration
	AppEnv  string `mapstructure:"APP_ENV"`
	AppPort int    `mapstructure:"APP_PORT"`
	
	// Logging Configuration
	EnableRequestLogging bool   `mapstructure:"ENABLE_REQUEST_LOGGING"`
	LogLevel            string `mapstructure:"LOG_LEVEL"`
	LogDirectory        string `mapstructure:"LOG_DIRECTORY"`
}

func Load() (*Config, error) {
	viper.SetDefault("GP_API_ENVIRONMENT", "sandbox")
	viper.SetDefault("GP_API_COUNTRY", "US")
	viper.SetDefault("GP_API_CURRENCY", "USD")
	viper.SetDefault("APP_ENV", "development")
	viper.SetDefault("APP_PORT", 8000)
	viper.SetDefault("ENABLE_REQUEST_LOGGING", false)
	viper.SetDefault("LOG_LEVEL", "info")
	viper.SetDefault("LOG_DIRECTORY", "logs")

	viper.AutomaticEnv()
	viper.SetConfigFile(".env")
	viper.SetConfigType("env")
	
	if err := viper.ReadInConfig(); err != nil {
		// .env file is optional, continue with environment variables
	}

	var config Config
	if err := viper.Unmarshal(&config); err != nil {
		return nil, err
	}

	validate := validator.New()
	if err := validate.Struct(&config); err != nil {
		return nil, err
	}

	return &config, nil
}

func (c *Config) IsProduction() bool {
	return c.GPApiEnvironment == "production"
}

func (c *Config) IsDevelopment() bool {
	return c.AppEnv == "development"
}

func (c *Config) GetGPAPIBaseURL() string {
	if c.IsProduction() {
		return "https://apis.globalpay.com/ucp"
	}
	return "https://apis.sandbox.globalpay.com/ucp"
}