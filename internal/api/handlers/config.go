package handlers

import (
	"net/http"
	"github.com/gin-gonic/gin"
	"go-payments-api/internal/config"
	"go-payments-api/internal/logger"
	"go-payments-api/internal/errors"
)

type ConfigHandler struct {
	config       *config.Config
	logger       *logger.Logger
	errorHandler *errors.ErrorHandler
}

type ConfigResponse struct {
	Success bool `json:"success"`
	Data    struct {
		AppID       string `json:"app_id"`
		Environment string `json:"environment"`
		Country     string `json:"country"`
		Currency    string `json:"currency"`
	} `json:"data"`
}

func NewConfigHandler(cfg *config.Config, log *logger.Logger, eh *errors.ErrorHandler) *ConfigHandler {
	return &ConfigHandler{
		config:       cfg,
		logger:       log,
		errorHandler: eh,
	}
}

func (h *ConfigHandler) GetConfig(c *gin.Context) {
	h.logger.Info("Config request received", nil, logger.ChannelAPI)

	response := ConfigResponse{
		Success: true,
	}
	
	response.Data.AppID = h.config.GPApiAppID // This is the app_id the JS expects
	response.Data.Environment = h.config.GPApiEnvironment
	response.Data.Country = h.config.GPApiCountry
	response.Data.Currency = h.config.GPApiCurrency

	c.Header("Access-Control-Allow-Origin", "*")
	c.Header("Access-Control-Allow-Methods", "GET, OPTIONS")
	c.Header("X-Content-Type-Options", "nosniff")
	c.Header("X-Frame-Options", "DENY")

	c.JSON(http.StatusOK, response)
}