package main

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/gin-contrib/cors"
	"github.com/gin-gonic/gin"
	
	"go-payments-api/internal/config"
	"go-payments-api/internal/logger"
	"go-payments-api/internal/errors"
	"go-payments-api/internal/api/handlers"
)

func main() {
	// Load configuration
	cfg, err := config.Load()
	if err != nil {
		log.Fatalf("Failed to load configuration: %v", err)
	}

	// Initialize logger
	lgr := logger.New(cfg.LogLevel, cfg.LogDirectory, false)
	lgr.Info("Starting Go Payments API", map[string]interface{}{
		"version":     "1.0.0",
		"environment": cfg.GPApiEnvironment,
	}, logger.ChannelSystem)

	// Initialize error handler
	errorHandler := errors.New(lgr, cfg.IsDevelopment())

	// Initialize router
	if cfg.IsProduction() {
		gin.SetMode(gin.ReleaseMode)
	}
	router := gin.Default()

	// Configure CORS
	corsConfig := cors.DefaultConfig()
	corsConfig.AllowAllOrigins = true
	corsConfig.AllowMethods = []string{"GET", "POST", "PUT", "DELETE", "OPTIONS"}
	corsConfig.AllowHeaders = []string{"Origin", "Content-Type", "Accept", "Authorization", "X-Requested-With"}
	corsConfig.AllowCredentials = true
	router.Use(cors.New(corsConfig))

	// Serve static files
	router.Static("/public", "/app/public")
	router.StaticFile("/", "/app/public/index.html")

	// Initialize handlers
	configHandler := handlers.NewConfigHandler(cfg, lgr, errorHandler)
	accessTokenHandler := handlers.NewAccessTokenHandler(cfg, lgr, errorHandler)
	verificationHandler := handlers.NewVerificationHandler(cfg, lgr, errorHandler)
	paymentHandler := handlers.NewPaymentHandler(cfg, lgr, errorHandler)
	transactionHandler := handlers.NewTransactionHandler(cfg, lgr, errorHandler)

	// Setup API routes
	api := router.Group("/api")
	{
		api.GET("/config", configHandler.GetConfig)
		api.POST("/get-access-token", accessTokenHandler.GetAccessToken)
		api.POST("/verify-card", verificationHandler.VerifyCard)
		api.POST("/process-payment", paymentHandler.ProcessPayment)
		api.GET("/transactions", transactionHandler.GetTransactions)
	}

	// Health endpoints
	router.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"status": "ok"})
	})
	
	router.GET("/ready", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"status": "ready"})
	})

	// Create server
	srv := &http.Server{
		Addr:    fmt.Sprintf(":%d", cfg.AppPort),
		Handler: router,
	}

	// Start server in a goroutine
	go func() {
		lgr.Info("Server starting", map[string]interface{}{
			"port": cfg.AppPort,
		}, logger.ChannelSystem)
		
		fmt.Printf("=== SERVER INFO ===\n")
		fmt.Printf("Starting server on port: %d\n", cfg.AppPort)
		fmt.Printf("GP-API App ID: %s\n", cfg.GPApiAppID)
		fmt.Printf("GP-API Environment: %s\n", cfg.GPApiEnvironment)
		fmt.Printf("==================\n")
		
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			lgr.Error("Failed to start server", map[string]interface{}{
				"error": err.Error(),
			}, logger.ChannelSystem)
			fmt.Printf("ERROR: Failed to start server: %v\n", err)
		}
	}()

	// Wait for interrupt signal to gracefully shutdown the server
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit
	lgr.Info("Server shutting down...", nil, logger.ChannelSystem)

	// Graceful shutdown
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	if err := srv.Shutdown(ctx); err != nil {
		lgr.Error("Server forced to shutdown", map[string]interface{}{
			"error": err.Error(),
		}, logger.ChannelSystem)
	}

	lgr.Info("Server exited", nil, logger.ChannelSystem)
}